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
    public function syncFromOrder(Order $order, ?OrderDetails $od = null): OrderDetails
    {
        $order->loadMissing('order_details');
        $od = $od ?? $order->order_details;

        if (! $od) {
            $od = OrderDetails::create(['order_id' => $order->id]);
        }

        $total = round((float) $order->net_total, 3);
        $paid = round(max(0, (float) ($order->prepaid_amount ?? 0)), 3);
        $remaining = round(max(0, $total - $paid), 3);

        $od->total_amount = $total;
        $od->paid_amount = $paid;
        $od->remaining_amount = $remaining;
        $od->amount_to_collect = $remaining <= 0.009 ? 0 : $remaining;

        // تحصيل إلكتروني مسبق (Paymob/valU/Sympl/Visa…): المبلغ المدفوع مديونية على شركة التحصيل
        // وإن كان لا يوجد متبقٍ عند التسليم — لا تُلغِ الربط ولا الذمة.
        $isElectronicCollection = $od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && $od->collection_provider_id;

        if ($remaining <= 0.009) {
            if ($isElectronicCollection && $paid > 0.009) {
                $od->collection_receivable_amount = $paid;
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
            } else {
                $od->collection_provider_type = CollectionProviderType::None->value;
                $od->collection_provider_id = null;
                $od->collection_status = OrderCollectionStatus::NotRequired->value;
                $od->settlement_status = OrderSettlementStatus::NotApplicable->value;
            }
        } elseif (! $od->collection_provider_type) {
            $od->collection_status = OrderCollectionStatus::Pending->value;
            $od->settlement_status = OrderSettlementStatus::Open->value;
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
        } elseif ((float) ($order->prepaid_amount ?? 0) >= (float) $order->net_total - 0.02) {
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
}
