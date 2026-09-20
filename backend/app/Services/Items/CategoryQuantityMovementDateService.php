<?php

namespace App\Services\Items;

use App\Models\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dates a manual quantity change so warehouse reports (categories_balance.created_at)
 * and inventory_movements show the stock in the chosen period.
 */
class CategoryQuantityMovementDateService
{
    public function parseFromRequest(Request $request): Carbon
    {
        $raw = $request->input('movement_date', $request->input('date'));

        return $this->parse($raw);
    }

    public function parse(mixed $raw): Carbon
    {
        if ($raw === null || $raw === '') {
            return now();
        }

        try {
            $parsed = Carbon::parse((string) $raw);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('تاريخ الحركة غير صالح.');
        }

        if ($parsed->gt(now()->endOfDay())) {
            throw new InvalidArgumentException('لا يمكن اختيار تاريخ في المستقبل.');
        }

        if ($parsed->isSameDay(now())) {
            return now();
        }

        return $parsed->copy()->setTime(12, 0, 0);
    }

    public function stampMovement(InventoryMovement $movement, Carbon $occurredAt): void
    {
        $movement->created_at = $occurredAt;
        $movement->updated_at = now();
        $movement->save();
    }

    /**
     * Rebuild balance_before / balance_after in created_at order so as-of-date
     * reports stay consistent after a back-dated adjustment.
     */
    public function recalculateRunningBalances(int $categoryId, float $currentQuantity): void
    {
        $rows = DB::table('categories_balance')
            ->where('category_id', $categoryId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'quantity']);

        if ($rows->isEmpty()) {
            return;
        }

        $sumDeltas = 0.0;
        foreach ($rows as $row) {
            $sumDeltas += (float) $row->quantity;
        }

        $running = $currentQuantity - $sumDeltas;

        foreach ($rows as $row) {
            $delta = (float) $row->quantity;
            $before = $running;
            $after = $before + $delta;
            DB::table('categories_balance')->where('id', $row->id)->update([
                'balance_before' => $before,
                'balance_after' => $after,
            ]);
            $running = $after;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceRowPayload(
        string $invoiceNumber,
        int $categoryId,
        string $type,
        float $delta,
        float $balanceBefore,
        float $balanceAfter,
        float $unitRef,
        string $by,
        Carbon $occurredAt,
    ): array {
        return [
            'invoice_number' => $invoiceNumber,
            'category_id' => $categoryId,
            'type' => $type,
            'quantity' => $delta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'price' => $unitRef,
            'total_price' => $unitRef * $delta,
            'unit_cost' => $unitRef,
            'cost_total' => $unitRef * $delta,
            'by' => $by,
            'created_at' => $occurredAt,
            'updated_at' => now(),
        ];
    }

    /**
     * Quantity as of $asOfEnd (inclusive datetime) = current − movements after that instant.
     */
    public function quantityAsOf(int $categoryId, float $currentQuantity, string $asOfEnd): float
    {
        $later = (float) DB::table('categories_balance')
            ->where('category_id', $categoryId)
            ->where('created_at', '>', $asOfEnd)
            ->sum('quantity');

        return round($currentQuantity - $later, 6);
    }

    public function attachDailyEntry(InventoryMovement $movement, ?int $dailyEntryId): void
    {
        if (! $dailyEntryId) {
            return;
        }
        $movement->daily_entry_id = $dailyEntryId;
        $movement->save();
    }
}
