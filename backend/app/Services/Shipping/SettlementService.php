<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Enums\OrderSettlementStatus;
use App\Enums\SettlementBatchStatus;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\shippingCompanyDetails;
use App\Support\CollectionProviderMorph;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SettlementService
{
    public function generateNumber(): string
    {
        $prefix = 'STL-' . now()->format('Ymd') . '-';

        $last = Settlement::query()
            ->where('settlement_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('settlement_number');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{provider_type: string, provider_id: int, period_from?: string, period_to?: string, notes?: string, order_ids?: int[]}  $input
     */
    public function createDraft(array $input): Settlement
    {
        $providerType = $input['provider_type'];
        $providerId = (int) $input['provider_id'];

        return DB::transaction(function () use ($input, $providerType, $providerId) {
            $settlement = Settlement::create([
                'settlement_number' => $this->generateNumber(),
                'provider_type' => $providerType,
                'provider_id' => $providerId,
                'status' => SettlementBatchStatus::Draft->value,
                'period_from' => $input['period_from'] ?? null,
                'period_to' => $input['period_to'] ?? null,
                'notes' => $input['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->attachOpenLines($settlement, $input['order_ids'] ?? null);

            return $settlement->fresh(['items.order']);
        });
    }

    /**
     * @param  ?list<int>  $onlyOrderIds
     */
    public function attachOpenLines(Settlement $settlement, ?array $onlyOrderIds = null): void
    {
        if (! $settlement->isDraft()) {
            throw new InvalidArgumentException('لا يمكن تعديل تسوية مُرحّلة.');
        }

        $shipCoId = $this->resolveOperationalCompanyId(
            $settlement->provider_type,
            (int) $settlement->provider_id
        );

        if (! $shipCoId) {
            throw new InvalidArgumentException('لا يوجد ربط تشغيلي لشركة التحصيل — اربط شركة التحصيل بشركة شحن أو اختر مندوب/شركة شحن.');
        }

        $q = shippingCompanyDetails::query()
            ->where('shipping_company_id', $shipCoId)
            ->where('is_done', 0)
            ->whereIn('status', ['تم شحن', 'تم التسليم']);

        if ($onlyOrderIds) {
            $q->whereIn('order_id', $onlyOrderIds);
        }

        if ($settlement->period_from) {
            $q->whereDate('shipping_date', '>=', $settlement->period_from);
        }
        if ($settlement->period_to) {
            $q->whereDate('shipping_date', '<=', $settlement->period_to);
        }

        $lines = $q->get();
        $totalCollected = 0.0;

        foreach ($lines as $line) {
            $exists = SettlementItem::query()
                ->where('shipping_company_detail_id', $line->id)
                ->whereHas('settlement', fn ($s) => $s->where('status', '!=', SettlementBatchStatus::Cancelled->value))
                ->exists();

            if ($exists) {
                continue;
            }

            $amt = (float) $line->amount;
            $totalCollected += $amt;

            SettlementItem::create([
                'settlement_id' => $settlement->id,
                'order_id' => $line->order_id,
                'shipping_company_detail_id' => $line->id,
                'order_amount' => $amt,
                'collected_amount' => $amt,
                'settled_amount' => 0,
                'reference_type' => 'shipping_company_details',
                'reference_id' => $line->id,
            ]);
        }

        $settlement->total_collected = round($totalCollected, 3);
        $settlement->remaining_balance = round($totalCollected, 3);
        $settlement->save();
    }

    public function post(Settlement $settlement, float $settledAmount): Settlement
    {
        if (! $settlement->isDraft()) {
            throw new InvalidArgumentException('التسوية ليست مسودة.');
        }

        $settledAmount = round($settledAmount, 3);
        if ($settledAmount <= 0) {
            throw new InvalidArgumentException('مبلغ التسوية يجب أن يكون أكبر من صفر.');
        }

        if ($settledAmount > (float) $settlement->total_collected + 0.02) {
            throw new InvalidArgumentException('مبلغ التسوية أكبر من إجمالي المستحق.');
        }

        return DB::transaction(function () use ($settlement, $settledAmount) {
            $remaining = $settledAmount;
            $items = $settlement->items()->orderBy('id')->get();

            foreach ($items as $item) {
                if ($remaining <= 0.009) {
                    break;
                }
                $lineAmt = (float) $item->collected_amount;
                $apply = round(min($lineAmt, $remaining), 3);
                $item->settled_amount = $apply;
                $item->save();
                $remaining -= $apply;

                if ($item->shipping_company_detail_id) {
                    $detail = shippingCompanyDetails::find($item->shipping_company_detail_id);
                    if ($detail && $apply >= $lineAmt - 0.02) {
                        // Full line settlement — mark for collection workflow (is_done stays until collect_order/voucher)
                    }
                }

                $order = $item->order()->with('order_details')->first();
                if ($order?->order_details) {
                    $od = $order->order_details;
                    $od->settlement_status = $apply >= $lineAmt - 0.02
                        ? OrderSettlementStatus::Settled->value
                        : OrderSettlementStatus::Partial->value;
                    $od->save();
                }
            }

            $settlement->total_settled = $settledAmount;
            $settlement->remaining_balance = round((float) $settlement->total_collected - $settledAmount, 3);
            $settlement->status = SettlementBatchStatus::Posted->value;
            $settlement->settled_at = now();
            $settlement->posted_by_user_id = auth()->id();
            $settlement->save();

            return $settlement->fresh(['items.order']);
        });
    }

    private function resolveOperationalCompanyId(string $providerType, int $providerId): ?int
    {
        $enum = CollectionProviderType::tryFrom($providerType);
        if ($enum && $enum->usesShippingCompanyLedger()) {
            return $providerId;
        }

        return CollectionProviderMorph::operationalShippingCompanyId($providerType, $providerId);
    }
}
