<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Enums\LiabilityHolderType;
use App\Enums\OrderCollectionStatus;
use App\Models\Order;
use App\Models\OrderLiabilityTransfer;
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

        $transferAmount = $amount ?? (float) ($od->remaining_amount ?? $order->net_total);
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
