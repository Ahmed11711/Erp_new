<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderDeliveryStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\Order;
use App\Models\OrderDetails;

/**
 * Syncs financial snapshot fields on order_details from order + legacy order_status.
 */
class OrderFinancialStateService
{
    public function __construct(
        private ShippingReceivableSplitService $splitService,
    ) {
    }

    public function syncFromOrder(Order $order, ?OrderDetails $od = null): OrderDetails
    {
        $order->loadMissing('order_details');
        $od = $od ?? $order->order_details;

        if (! $od) {
            $od = OrderDetails::create(['order_id' => $order->id]);
        }

        if (in_array((string) $order->order_status, ['ملغي', 'أرشيف'], true)) {
            $od->total_amount = round((float) $order->net_total, 3);
            $od->paid_amount = round(max(0, (float) ($order->prepaid_amount ?? 0)), 3);
            $od->remaining_amount = 0;
            $od->amount_to_collect = 0;
            $od->shipping_receivable_amount = null;
            $od->collection_receivable_amount = null;
            $od->collection_provider_type = CollectionProviderType::None->value;
            $od->collection_provider_id = null;
            $od->collection_company_id = null;
            $od->collection_status = OrderCollectionStatus::NotRequired->value;
            $od->settlement_status = OrderSettlementStatus::NotApplicable->value;
            $this->mapLegacyOrderStatus($order->order_status, $od);
            $od->save();

            return $od;
        }

        $total = round((float) $order->net_total, 3);
        $paid = round(max(0, (float) ($order->prepaid_amount ?? 0)), 3);
        $remaining = $this->splitService->codRemaining($total, $paid);

        $od->total_amount = $total;
        $od->paid_amount = $paid;
        $od->remaining_amount = $remaining;
        $od->amount_to_collect = $remaining <= 0.009 ? 0 : $remaining;

        // تحصيل إلكتروني مسبق (Paymob/valU/Sympl/Visa…): المبلغ المدفوع مديونية على شركة التحصيل
        $isElectronicCollection = $this->hasElectronicCollectionReceivable($order, $od);

        $alreadyCollected = $od->collection_status === OrderCollectionStatus::Collected->value
            || $od->settlement_status === OrderSettlementStatus::Settled->value
            || $order->order_status === 'تم التحصيل';

        if ($remaining <= 0.009) {
            if ($isElectronicCollection && $paid > 0.009) {
                $this->ensureElectronicCollectionProvider($order, $od);

                if ($alreadyCollected) {
                    $od->collection_receivable_amount = 0;
                    $od->amount_to_collect = 0;
                } else {
                    $od->collection_receivable_amount = $paid;
                    // 0 وليس null حتى لا يُفسَّر لاحقاً كتقسيم يدوي ناقص.
                    $od->shipping_receivable_amount = 0;
                    $this->markElectronicCollectionPending($od);
                }
            } else {
                if (! $this->hasElectronicCollectionReceivable($order, $od)) {
                    $od->collection_provider_type = CollectionProviderType::None->value;
                    $od->collection_provider_id = null;
                    $od->collection_status = OrderCollectionStatus::NotRequired->value;
                    $od->settlement_status = OrderSettlementStatus::NotApplicable->value;
                }
            }
        } else {
            if ($isElectronicCollection && $paid > 0.009 && ! $alreadyCollected) {
                $this->ensureElectronicCollectionProvider($order, $od);
                $this->repairMixedPrepaidCodSplit($od, $remaining, $paid, $total);
                $this->markElectronicCollectionPending($od);
            } elseif (! $od->collection_provider_type) {
                $od->collection_status = OrderCollectionStatus::Pending->value;
                $od->settlement_status = OrderSettlementStatus::Open->value;
            }
        }

        if ($od->shipping_company_id && ! $od->shipping_provider_id) {
            $od->shipping_provider_id = $od->shipping_company_id;
        }

        $this->mapLegacyOrderStatus($order->order_status, $od);

        $od->save();

        return $od;
    }

    public function mapLegacyOrderStatus(string $orderStatus, OrderDetails $od): void
    {
        $delivery = match ($orderStatus) {
            'طلب جديد', 'جديد', 'طلب مؤكد', 'مؤجل' => OrderDeliveryStatus::Pending,
            'شحن جزئي' => OrderDeliveryStatus::Partial,
            'تسليم جزئي' => OrderDeliveryStatus::PartiallyDelivered,
            'تم شحن' => OrderDeliveryStatus::Shipped,
            'تم التسليم' => OrderDeliveryStatus::Delivered,
            'رفض استلام' => OrderDeliveryStatus::Refused,
            'ملغي' => OrderDeliveryStatus::Cancelled,
            default => OrderDeliveryStatus::tryFrom($od->delivery_status ?? '') ?? OrderDeliveryStatus::Pending,
        };

        $collection = match ($orderStatus) {
            'تم التحصيل' => OrderCollectionStatus::Collected,
            'رفض استلام' => OrderCollectionStatus::Refused,
            default => null,
        };

        if ($od->liability_transferred_at) {
            $collection = OrderCollectionStatus::Transferred;
        }

        $od->delivery_status = $delivery->value;

        if ($collection) {
            $od->collection_status = $collection->value;
        }

        if ($orderStatus === 'تم التحصيل') {
            $od->paid_amount = $od->total_amount;
            $od->remaining_amount = 0;
            $od->amount_to_collect = 0;
            $od->collection_receivable_amount = 0;
            $od->settlement_status = OrderSettlementStatus::Settled->value;
        }
    }

    public function inferCollectionProviderOnShip(
        Order $order,
        int $shippingProviderId,
        ?int $legacyCollectionCompanyId,
        ?string $explicitType = null,
        ?int $explicitId = null,
    ): void {
        $od = $order->order_details;
        if (! $od) {
            return;
        }

        $od->shipping_provider_id = $shippingProviderId;
        $od->shipping_company_id = $shippingProviderId;

        if ($explicitType && $explicitId) {
            $od->collection_provider_type = $explicitType;
            $od->collection_provider_id = $explicitId;
        } elseif ($legacyCollectionCompanyId) {
            $od->collection_provider_type = CollectionProviderType::ShippingCompany->value;
            $od->collection_provider_id = $legacyCollectionCompanyId;
            $od->collection_company_id = $legacyCollectionCompanyId;
        } elseif ($this->shouldPreserveElectronicCollectionProvider($order, $od)) {
            if ($od->collection_provider_type !== CollectionProviderType::CollectionCompany->value
                || ! $od->collection_provider_id) {
                app(CollectionCompanyForOrderResolver::class)->resolve($order, persistLinkIfMissing: true);
                $od->refresh();
            }
        } elseif ($this->splitService->codRemaining(
            (float) $order->net_total,
            (float) ($order->prepaid_amount ?? 0)
        ) <= 0.02) {
            $od->collection_provider_type = CollectionProviderType::None->value;
            $od->collection_provider_id = null;
        } else {
            $shipCo = \App\Models\ShippingCompany::find($shippingProviderId);
            $type = ($shipCo && $shipCo->type === 'مندوب')
                ? CollectionProviderType::Courier->value
                : CollectionProviderType::ShippingCompany->value;
            $od->collection_provider_type = $type;
            $od->collection_provider_id = $shippingProviderId;
        }

        $legacyId = \App\Support\CollectionProviderMorph::legacyCollectionCompanyId(
            $od->collection_provider_type,
            $od->collection_provider_id ? (int) $od->collection_provider_id : null
        );
        if ($legacyId) {
            $od->collection_company_id = $legacyId;
        }

        $this->syncFromOrder($order, $od);
    }

    /**
     * بعد إضافة صنف كاش على أوردر مدفوع أونلاين تبقى لقطة التحصيل ناقصة
     * (collection_receivable فقط). نُكمل المبلغين: Paymob = prepaid، المندوب = المتبقي.
     */
    private function repairMixedPrepaidCodSplit(OrderDetails $od, float $remaining, float $paid, float $net): void
    {
        $incomplete = ($od->shipping_receivable_amount === null) xor ($od->collection_receivable_amount === null);
        $prepaidExceedsNet = $this->splitService->prepaidExceedsNet($net, $paid);

        if (! $incomplete && ! $prepaidExceedsNet) {
            return;
        }

        $od->collection_receivable_amount = $paid;
        $od->shipping_receivable_amount = $remaining;
    }

    private function markElectronicCollectionPending(OrderDetails $od): void
    {
        if (! in_array($od->collection_status, [
            OrderCollectionStatus::Collected->value,
            OrderCollectionStatus::Transferred->value,
            OrderCollectionStatus::Refused->value,
            OrderCollectionStatus::Partial->value,
        ], true)) {
            $od->collection_status = OrderCollectionStatus::Pending->value;
        }
        if (! $od->settlement_status || $od->settlement_status === OrderSettlementStatus::NotApplicable->value) {
            $od->settlement_status = OrderSettlementStatus::Open->value;
        }
    }

    private function shouldPreserveElectronicCollectionProvider(Order $order, OrderDetails $od): bool
    {
        return $this->hasElectronicCollectionReceivable($order, $od);
    }

    private function ensureElectronicCollectionProvider(Order $order, OrderDetails $od): void
    {
        if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $od->collection_provider_id) {
            return;
        }

        $company = app(CollectionCompanyForOrderResolver::class)->resolve($order, persistLinkIfMissing: true);
        if (! $company) {
            return;
        }

        $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
        $od->collection_provider_id = (int) $company->id;
        if ($company->linked_shipping_company_id) {
            $od->collection_company_id = (int) $company->linked_shipping_company_id;
        }
    }

    private function hasElectronicCollectionReceivable(Order $order, OrderDetails $od): bool
    {
        if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $od->collection_provider_id) {
            return true;
        }

        if ((float) ($od->collection_receivable_amount ?? 0) > 0.009) {
            return true;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'shopify_payment_gateway')
            && trim((string) ($order->shopify_payment_gateway ?? '')) !== '') {
            return true;
        }

        $prepaid = (float) ($order->prepaid_amount ?? 0);
        if ($prepaid > 0.009
            && ! in_array(trim((string) ($order->prepaid_payment_type ?? '')), ['bank', 'safe', 'service_account'], true)
            && app(CollectionReceivableAccountResolver::class)->receivableAccountIdForOrderDetails($od)) {
            return true;
        }

        return false;
    }
}
