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
use App\Services\Accounting\DeliveryConfirmationAccountingService;
use Illuminate\Support\Facades\DB;

class OrderLiabilityTransferService
{
    public function __construct(
        private DeliveryConfirmationAccountingService $deliveryAccounting,
        private OrderFinancialStateService $financialState,
    ) {
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
    ): ?OrderLiabilityTransfer {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od || $od->liability_transferred_at) {
            return null;
        }

        $transferAmount = $this->resolveTransferAmount($order, $od);
        if ($transferAmount <= 0.009) {
            return null;
        }

        $resolved = $this->resolveHolderForDelivery($order);
        if ($resolved === null) {
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
