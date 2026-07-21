<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Enums\LiabilityHolderType;
use App\Enums\OrderCollectionStatus;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderLiabilityTransfer;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use App\Services\Accounting\DeliveryConfirmationAccountingService;
use Illuminate\Support\Facades\DB;

class OrderLiabilityTransferService
{
    public function __construct(
        private DeliveryConfirmationAccountingService $deliveryAccounting,
        private OrderFinancialStateService $financialState,
        private ShippingReceivableSplitService $splitService,
    ) {
    }

    /**
     * يطبّق الجهة المختارة (قد تكون شركة مختلفة عن المرتبطة بالطلب) على تفاصيل الطلب،
     * حتى تُرمى المديونية على الشركة المختارة في القيود والرصيد التشغيلي.
     */
    public function applyChosenHolder(Order $order, LiabilityHolderType $holder, ?int $holderId): void
    {
        if (! $holderId || $holderId <= 0) {
            return;
        }

        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            return;
        }

        if (
            $holder === LiabilityHolderType::Courier
            || $holder === LiabilityHolderType::ShippingCompany
        ) {
            $company = ShippingCompany::find($holderId);
            if (! $company) {
                throw new \RuntimeException('شركة الشحن/المندوب المختارة غير موجودة.');
            }
            $od->shipping_provider_id = (int) $company->id;
            $od->shipping_company_id = (int) $company->id;
            $od->unsetRelation('shipping_company');
            $od->save();

            return;
        }

        if ($holder === LiabilityHolderType::CollectionCompany) {
            $company = CollectionCompany::find($holderId);
            if (! $company) {
                throw new \RuntimeException('شركة التحصيل المختارة غير موجودة.');
            }
            $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
            $od->collection_provider_id = (int) $company->id;
            if ($company->linked_shipping_company_id) {
                $od->collection_company_id = (int) $company->linked_shipping_company_id;
            }
            $od->unsetRelation('collection_company');
            $od->save();
        }
    }

    /**
     * إنشاء سطور المستحقات التشغيلية عند التسليم/نقل الذمة (رمي المديونية على
     * شركة الشحن/المندوب/شركة التحصيل بحالة «تم التسليم»). لا تُنشأ عند الشحن.
     */
    public function ensureDeliveryLines(Order $order): void
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            return;
        }

        // طلبات الشركات تُدار مديونيتها عبر رصيد العميل-الشركة، لا عبر مستحقات شركات الشحن.
        if ($order->customer_type === 'شركة') {
            return;
        }

        if (shippingCompanyDetails::where('order_id', $order->id)->exists()) {
            return;
        }

        $courierId = (int) ($od->shipping_provider_id ?? $od->shipping_company_id ?? 0);
        if ($courierId <= 0) {
            return;
        }

        $net = round((float) ($order->net_total ?? 0), 3);
        if ($net <= 0.0001) {
            return;
        }

        $collectionCompanyId = $od->collection_company_id ? (int) $od->collection_company_id : null;
        $shippingDate = $od->shipping_date ? (string) $od->shipping_date : now()->format('Y-m-d');
        $manualShip = $od->shipping_receivable_amount !== null ? (float) $od->shipping_receivable_amount : null;
        $manualColl = $od->collection_receivable_amount !== null ? (float) $od->collection_receivable_amount : null;

        $split = $this->splitService->resolveForShip(
            $order,
            $courierId,
            $collectionCompanyId,
            $manualShip,
            $manualColl,
            null,
        );

        $segments = $this->splitService->segmentsForProcedureCalls(
            $courierId,
            $split['collection_company_id'],
            $split['shipping_amount'],
            $split['collection_amount'],
        );

        foreach ($segments as $seg) {
            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                $seg['company_id'],
                (int) $order->id,
                $shippingDate,
                'تم التسليم',
                $seg['amount'],
                auth()->user()->name ?? 'system',
                now(),
            ]);
        }

        $od->shipping_receivable_amount = $split['shipping_amount'];
        $od->collection_receivable_amount = $split['collection_amount'];
        $od->save();
    }

    /**
     * Transfer customer liability to courier / shipping company / collection company.
     *
     * @param  LiabilityHolderType  $toHolder
     * @return array{transfer: OrderLiabilityTransfer, gl_batch: string}
     */
    public function transfer(
        Order $order,
        LiabilityHolderType $toHolder,
        int $toHolderId,
        ?float $amount = null,
        ?string $reason = null,
    ): array {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            throw new \RuntimeException('الطلب بدون تفاصيل شحن.');
        }

        if ($od->liability_transferred_at) {
            throw new \RuntimeException('تم نقل الذمة مسبقاً لهذا الطلب.');
        }

        if (! in_array($order->order_status, ['تم شحن', 'شحن جزئي', 'تم التسليم'], true)) {
            throw new \RuntimeException('لا يمكن نقل الذمة في الحالة: ' . $order->order_status);
        }

        $transferAmount = $this->resolveTransferAmount($order, $od, $amount);
        if ($transferAmount <= 0.009) {
            throw new \RuntimeException('لا يوجد مبلغ لنقل الذمة.');
        }

        return DB::transaction(function () use ($order, $od, $toHolder, $toHolderId, $transferAmount, $reason) {
            // المديونية تُرمى الآن على الشركة المختارة — ربط حساب الذمم ثم إنشاء المستحقات التشغيلية.
            if ($order->customer_type !== 'شركة') {
                app(ShipmentReceivableAccountGuard::class)->assertLinkedForDelivery($order);
                $this->ensureDeliveryLines($order);
            }

            $gl = $this->deliveryAccounting->recordDeliveryReceivableTransfer($order);

            $transfer = OrderLiabilityTransfer::create([
                'order_id' => $order->id,
                'from_holder_type' => LiabilityHolderType::Customer->value,
                'from_holder_id' => null,
                'to_holder_type' => $toHolder->value,
                'to_holder_id' => $toHolderId,
                'amount' => $transferAmount,
                'reason' => $reason ?? 'transfer_liability',
                'gl_batch_code' => $gl['batch_code'] ?? null,
                'performed_by_user_id' => auth()->id(),
            ]);

            $od->liability_holder_type = $toHolder->value;
            $od->liability_holder_id = $toHolderId;
            $od->liability_transferred_at = now();

            [$providerType, $providerId] = $this->holderToCollectionProvider($toHolder, $toHolderId);
            if ($providerType) {
                $od->collection_provider_type = $providerType;
                $od->collection_provider_id = $providerId;
            }

            $od->collection_status = OrderCollectionStatus::Transferred->value;
            $od->save();

            if ($order->order_status === 'تم شحن') {
                $order->order_status = 'تم التسليم';
                $order->save();
            }

            $this->financialState->syncFromOrder($order->fresh(['order_details']), $od);

            return [
                'transfer' => $transfer,
                'gl_batch' => $gl['batch_code'] ?? '',
            ];
        });
    }

    /**
     * تسجيل نقل الذمة التشغيلي بعد تأكيد التسليم (بدون تكرار قيود GL — تُمرَّر من deliver).
     */
    public function recordOperationalTransferOnDelivery(
        Order $order,
        string $glBatchCode,
        string $reason = 'delivery_confirm',
        ?LiabilityHolderType $preferredHolder = null,
        ?int $preferredHolderId = null,
    ): ?OrderLiabilityTransfer {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od || $od->liability_transferred_at) {
            return null;
        }

        // طلبات الشركات بدون جهة شحن: الذمة تبقى على عميل الشركة
        if ($order->customer_type === 'شركة'
            && $preferredHolder === null
            && (int) ($od->shipping_company_id ?? $od->shipping_provider_id ?? 0) <= 0) {
            return null;
        }

        $transferAmount = $this->resolveTransferAmount($order, $od);
        if ($transferAmount <= 0.009) {
            return null;
        }

        $resolved = $preferredHolder !== null
            ? $this->resolveManualTransferHolder($order, $preferredHolder, $preferredHolderId)
            : $this->resolveHolderForDelivery($order);
        if ($resolved === null) {
            if ($order->customer_type === 'شركة') {
                return null;
            }
            throw new \RuntimeException('تعذر تحديد شركة الشحن أو المندوب لنقل الذمة عند التسليم.');
        }

        [$toHolder, $toHolderId] = $resolved;

        $transfer = OrderLiabilityTransfer::create([
            'order_id' => $order->id,
            'from_holder_type' => LiabilityHolderType::Customer->value,
            'from_holder_id' => null,
            'to_holder_type' => $toHolder->value,
            'to_holder_id' => $toHolderId,
            'amount' => $transferAmount,
            'reason' => $reason,
            'gl_batch_code' => $glBatchCode !== '' ? $glBatchCode : null,
            'performed_by_user_id' => auth()->id(),
        ]);

        $od->liability_holder_type = $toHolder->value;
        $od->liability_holder_id = $toHolderId;
        $od->liability_transferred_at = now();

        [$providerType, $providerId] = $this->holderToCollectionProvider($toHolder, $toHolderId);
        if ($providerType) {
            $od->collection_provider_type = $providerType;
            $od->collection_provider_id = $providerId;
        }

        $od->collection_status = OrderCollectionStatus::Transferred->value;
        $od->save();

        $this->financialState->syncFromOrder($order->fresh(['order_details']), $od);

        return $transfer;
    }

    /**
     * المبلغ القابل لنقل الذمة: COD (remaining) أو ذمة التحصيل للمدفوع مقدماً.
     */
    public function resolveTransferAmount(Order $order, ?OrderDetails $od = null, ?float $amount = null): float
    {
        if ($amount !== null && $amount > 0.009) {
            return round($amount, 3);
        }

        $order->loadMissing('order_details');
        $od = $od ?? $order->order_details;
        if (! $od) {
            return 0.0;
        }

        $remaining = round(max(0, (float) ($od->remaining_amount ?? 0)), 3);
        if ($remaining > 0.009) {
            return $remaining;
        }

        $collectionReceivable = round(max(0, (float) ($od->collection_receivable_amount ?? 0)), 3);
        if ($collectionReceivable > 0.009) {
            return $collectionReceivable;
        }

        return round(max(0, (float) ($order->net_total ?? 0)), 3);
    }

    public function canTransferLiability(Order $order, ?OrderDetails $od = null): bool
    {
        $order->loadMissing('order_details');
        $od = $od ?? $order->order_details;

        if (! $od || $od->liability_transferred_at) {
            return false;
        }

        if (! in_array($order->order_status, ['تم شحن', 'شحن جزئي', 'تم التسليم'], true)) {
            return false;
        }

        return $this->resolveTransferAmount($order, $od) > 0.009;
    }

    /**
     * @return array{0: LiabilityHolderType, 1: int}|null
     */
    public function resolveManualTransferHolder(
        Order $order,
        ?LiabilityHolderType $preferred = null,
        ?int $preferredId = null,
    ): ?array {
        $order->loadMissing('order_details.shipping_company');
        $od = $order->order_details;
        if (! $od) {
            return null;
        }

        if ($preferred === LiabilityHolderType::CollectionCompany) {
            return $this->resolveCollectionCompanyHolder($order, $od, $preferredId);
        }

        if ($preferred === LiabilityHolderType::Courier || $preferred === LiabilityHolderType::ShippingCompany) {
            return $this->resolveShippingHolder($od);
        }

        return $this->resolveHolderForDelivery($order);
    }

    /**
     * @return array{0: LiabilityHolderType, 1: int}|null
     */
    private function resolveShippingHolder(OrderDetails $od): ?array
    {
        $shippingId = (int) ($od->shipping_provider_id ?? $od->shipping_company_id ?? 0);
        if ($shippingId <= 0) {
            return null;
        }

        $shippingCo = $od->shipping_company ?? ShippingCompany::find($shippingId);

        return [
            ($shippingCo && $shippingCo->type === 'مندوب')
                ? LiabilityHolderType::Courier
                : LiabilityHolderType::ShippingCompany,
            $shippingId,
        ];
    }

    public function isPrepaidCollectionOrder(Order $order, ?OrderDetails $od = null): bool
    {
        $order->loadMissing('order_details');
        $od = $od ?? $order->order_details;
        if (! $od) {
            return false;
        }

        $remaining = round(max(0, (float) ($od->remaining_amount ?? 0)), 3);
        $collectionReceivable = round(max(0, (float) ($od->collection_receivable_amount ?? 0)), 3);

        return $remaining <= 0.009 && $collectionReceivable > 0.009;
    }

    /**
     * @return array{0: LiabilityHolderType, 1: int}|null
     */
    private function resolveCollectionCompanyHolder(
        Order $order,
        OrderDetails $od,
        ?int $preferredId = null,
    ): ?array {
        if ($preferredId && $preferredId > 0) {
            $company = CollectionCompany::find($preferredId);
            if ($company) {
                $this->persistCollectionCompanyLink($od, $company);

                return [LiabilityHolderType::CollectionCompany, (int) $company->id];
            }
        }

        if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $od->collection_provider_id) {
            return [LiabilityHolderType::CollectionCompany, (int) $od->collection_provider_id];
        }

        $company = app(CollectionCompanyForOrderResolver::class)->resolve($order, persistLinkIfMissing: true);
        if (! $company) {
            return null;
        }

        $this->persistCollectionCompanyLink($od, $company);

        return [LiabilityHolderType::CollectionCompany, (int) $company->id];
    }

    private function persistCollectionCompanyLink(OrderDetails $od, CollectionCompany $company): void
    {
        $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
        $od->collection_provider_id = (int) $company->id;
        if ($company->linked_shipping_company_id) {
            $od->collection_company_id = (int) $company->linked_shipping_company_id;
        }
        $od->save();
    }

    /**
     * @return array{0: LiabilityHolderType, 1: int}|null
     */
    public function resolveHolderForDelivery(Order $order): ?array
    {
        $order->loadMissing('order_details.shipping_company');
        $od = $order->order_details;
        if (! $od) {
            return null;
        }

        $shipAmount = (float) ($od->shipping_receivable_amount ?? 0);
        $collAmount = (float) ($od->collection_receivable_amount ?? 0);

        if (
            $collAmount > $shipAmount + 0.009
        ) {
            if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
                && $od->collection_provider_id) {
                return [LiabilityHolderType::CollectionCompany, (int) $od->collection_provider_id];
            }

            $company = app(CollectionCompanyForOrderResolver::class)->resolve($order, persistLinkIfMissing: true);
            if ($company) {
                return [LiabilityHolderType::CollectionCompany, (int) $company->id];
            }
        }

        $shippingId = (int) ($od->shipping_provider_id ?? $od->shipping_company_id ?? 0);
        if ($shippingId > 0) {
            $shippingCo = $od->shipping_company ?? ShippingCompany::find($shippingId);

            return [
                ($shippingCo && $shippingCo->type === 'مندوب')
                    ? LiabilityHolderType::Courier
                    : LiabilityHolderType::ShippingCompany,
                $shippingId,
            ];
        }

        if ($od->collection_provider_id && $od->collection_provider_type) {
            return match ($od->collection_provider_type) {
                CollectionProviderType::CollectionCompany->value => [
                    LiabilityHolderType::CollectionCompany,
                    (int) $od->collection_provider_id,
                ],
                CollectionProviderType::Courier->value => [
                    LiabilityHolderType::Courier,
                    (int) $od->collection_provider_id,
                ],
                CollectionProviderType::ShippingCompany->value => [
                    LiabilityHolderType::ShippingCompany,
                    (int) $od->collection_provider_id,
                ],
                default => null,
            };
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private function holderToCollectionProvider(LiabilityHolderType $holder, int $id): array
    {
        return match ($holder) {
            LiabilityHolderType::ShippingCompany => [CollectionProviderType::ShippingCompany->value, $id],
            LiabilityHolderType::Courier => [CollectionProviderType::Courier->value, $id],
            LiabilityHolderType::CollectionCompany => [CollectionProviderType::CollectionCompany->value, $id],
            default => [null, null],
        };
    }
}
