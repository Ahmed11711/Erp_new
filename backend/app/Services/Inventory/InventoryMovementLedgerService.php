<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Stock;
use App\Services\CategoryInventoryCostService;
use Illuminate\Support\Facades\DB;

/**
 * All persistent quantity changes must flow through this service so rows exist in inventory_movements.
 */
class InventoryMovementLedgerService
{
    public function refreshMirrorBalances(int $categoryId): void
    {
        $cat = Category::query()->find($categoryId);
        if (! $cat) {
            return;
        }
        $qty = (float) ($cat->quantity ?? 0);
        $costVal = (float) ($cat->total_price ?? 0);

        InventoryBalance::query()->updateOrCreate(
            ['category_id' => $categoryId],
            [
                'stock_id' => $cat->stock_id,
                'quantity' => $qty,
                'cost_value' => $costVal,
            ]
        );
    }

    /**
     * Inbound: increases quantity. Optionally posts valuation change on the category row.
     *
     * @param  float|string  $quantity  Absolute quantity (positive).
     */
    public function recordInbound(
        Category $category,
        InventoryMovementType $type,
        float|string $quantity,
        ?float $unitCost,
        ?float $totalCost,
        bool $adjustCategoryValuation = true,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $dailyEntryId = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $category,
            $type,
            $quantity,
            $unitCost,
            $totalCost,
            $adjustCategoryValuation,
            $referenceType,
            $referenceId,
            $reason,
            $dailyEntryId,
            $performedBy
        ) {
            $cat = Category::query()->lockForUpdate()->findOrFail($category->id);
            $qtyStr = $this->normalizeQty($quantity);
            if (bccomp($qtyStr, '0', 6) <= 0) {
                throw new \InvalidArgumentException('Inbound quantity must be positive.');
            }

            $tc = $totalCost !== null ? (float) $totalCost : (($unitCost !== null ? (float) $unitCost * (float) $qtyStr : 0.0));
            $uc = $unitCost !== null ? (float) $unitCost : (((float) $qtyStr) > 0 ? $tc / (float) $qtyStr : 0.0);

            $prevQty = (string) ($cat->quantity ?? '0');
            $cat->quantity = bcadd($prevQty, $qtyStr, 6);

            if ($adjustCategoryValuation && abs($tc) > 0.0000001) {
                $cat->total_price = round(((float) ($cat->total_price ?? 0)) + $tc, 4);
                CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $cat->id);
            }

            $cat->save();

            $stock = $cat->stock_id ? Stock::query()->find($cat->stock_id) : null;

            $movement = InventoryMovement::query()->create([
                'category_id' => $cat->id,
                'stock_id' => $stock?->id,
                'warehouse_name' => $stock?->name ?? (string) $cat->warehouse,
                'direction' => 'in',
                'movement_type' => $type->value,
                'quantity' => $qtyStr,
                'unit_cost' => $uc,
                'total_cost' => $tc,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'daily_entry_id' => $dailyEntryId,
                'reason' => $reason,
                'performed_by' => $performedBy ?? $this->defaultActor(),
            ]);

            $this->refreshMirrorBalances((int) $cat->id);

            return $movement;
        });
    }

    /**
     * Outbound: decreases quantity.
     *
     * @param  bool  $adjustCategoryValuation  When false, only quantity changes (e.g. some shipment flows rely on COGS elsewhere).
     */
    public function recordOutbound(
        Category $category,
        InventoryMovementType $type,
        float|string $quantity,
        ?float $unitCost,
        ?float $totalCost,
        bool $adjustCategoryValuation = true,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $dailyEntryId = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $category,
            $type,
            $quantity,
            $unitCost,
            $totalCost,
            $adjustCategoryValuation,
            $referenceType,
            $referenceId,
            $reason,
            $dailyEntryId,
            $performedBy
        ) {
            $cat = Category::query()->lockForUpdate()->findOrFail($category->id);
            $qtyStr = $this->normalizeQty($quantity);
            if (bccomp($qtyStr, '0', 6) <= 0) {
                throw new \InvalidArgumentException('Outbound quantity must be positive.');
            }

            $current = (string) ($cat->quantity ?? '0');
            if (bccomp($current, $qtyStr, 6) < 0) {
                throw new \RuntimeException('Insufficient quantity for category #' . $cat->id);
            }

            $avg = $unitCost ?? CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
            $tc = $totalCost !== null ? (float) $totalCost : ((float) $qtyStr) * (float) $avg;

            $cat->quantity = bcsub($current, $qtyStr, 6);

            if ($adjustCategoryValuation && abs($tc) > 0.0000001) {
                $cat->total_price = round(max(0, ((float) ($cat->total_price ?? 0)) - $tc), 4);
                CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $cat->id);
            }

            $cat->save();

            $stock = $cat->stock_id ? Stock::query()->find($cat->stock_id) : null;

            $movement = InventoryMovement::query()->create([
                'category_id' => $cat->id,
                'stock_id' => $stock?->id,
                'warehouse_name' => $stock?->name ?? (string) $cat->warehouse,
                'direction' => 'out',
                'movement_type' => $type->value,
                'quantity' => $qtyStr,
                'unit_cost' => (float) $avg,
                'total_cost' => $tc,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'daily_entry_id' => $dailyEntryId,
                'reason' => $reason,
                'performed_by' => $performedBy ?? $this->defaultActor(),
            ]);

            $this->refreshMirrorBalances((int) $cat->id);

            return $movement;
        });
    }

    /**
     * Use when category quantities and valuation were already updated by legacy FIFO / manufacturing logic.
     * Writes audit rows to inventory_movements and refreshes inventory_balances mirror.
     *
     * @param  float|string  $quantity  Absolute quantity (positive).
     */
    public function appendOutboundMovement(
        Category $category,
        InventoryMovementType $type,
        float|string $quantity,
        float $unitCost,
        float $totalCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $dailyEntryId = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $category,
            $type,
            $quantity,
            $unitCost,
            $totalCost,
            $referenceType,
            $referenceId,
            $reason,
            $dailyEntryId,
            $performedBy
        ) {
            $cat = Category::query()->findOrFail($category->id);
            $qtyStr = $this->normalizeQty($quantity);

            $stock = $cat->stock_id ? Stock::query()->find($cat->stock_id) : null;

            $movement = InventoryMovement::query()->create([
                'category_id' => $cat->id,
                'stock_id' => $stock?->id,
                'warehouse_name' => $stock?->name ?? (string) $cat->warehouse,
                'direction' => 'out',
                'movement_type' => $type->value,
                'quantity' => $qtyStr,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'daily_entry_id' => $dailyEntryId,
                'reason' => $reason,
                'performed_by' => $performedBy ?? $this->defaultActor(),
            ]);

            $this->refreshMirrorBalances((int) $cat->id);

            return $movement;
        });
    }

    /**
     * @param  float|string  $quantity  Absolute quantity (positive).
     */
    public function appendInboundMovement(
        Category $category,
        InventoryMovementType $type,
        float|string $quantity,
        float $unitCost,
        float $totalCost,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        ?int $dailyEntryId = null,
        ?string $performedBy = null,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $category,
            $type,
            $quantity,
            $unitCost,
            $totalCost,
            $referenceType,
            $referenceId,
            $reason,
            $dailyEntryId,
            $performedBy
        ) {
            $cat = Category::query()->findOrFail($category->id);
            $qtyStr = $this->normalizeQty($quantity);

            $stock = $cat->stock_id ? Stock::query()->find($cat->stock_id) : null;

            $movement = InventoryMovement::query()->create([
                'category_id' => $cat->id,
                'stock_id' => $stock?->id,
                'warehouse_name' => $stock?->name ?? (string) $cat->warehouse,
                'direction' => 'in',
                'movement_type' => $type->value,
                'quantity' => $qtyStr,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'daily_entry_id' => $dailyEntryId,
                'reason' => $reason,
                'performed_by' => $performedBy ?? $this->defaultActor(),
            ]);

            $this->refreshMirrorBalances((int) $cat->id);

            return $movement;
        });
    }

    private function normalizeQty(float|string $quantity): string
    {
        return sprintf('%.6F', (float) $quantity);
    }

    private function defaultActor(): ?string
    {
        try {
            return auth()->check() ? auth()->user()->name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
