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
     * @param  bool  $allowNegative  When true, quantity may go below zero (e.g. purchase invoice delete reversal).
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
        bool $allowNegative = false,
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
            $performedBy,
            $allowNegative
        ) {
            $cat = Category::query()->lockForUpdate()->findOrFail($category->id);
            $qtyStr = $this->normalizeQty($quantity);
            if (bccomp($qtyStr, '0', 6) <= 0) {
                throw new \InvalidArgumentException('Outbound quantity must be positive.');
            }

            $current = (string) ($cat->quantity ?? '0');
            if (! $allowNegative && bccomp($current, $qtyStr, 6) < 0) {
                throw new \RuntimeException('Insufficient quantity for category #' . $cat->id);
            }

            $avg = $unitCost ?? CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
            $tc = $totalCost !== null ? (float) $totalCost : ((float) $qtyStr) * (float) $avg;

            $cat->quantity = bcsub($current, $qtyStr, 6);

            if ($adjustCategoryValuation && abs($tc) > 0.0000001) {
                $newTotalPrice = ((float) ($cat->total_price ?? 0)) - $tc;
                $cat->total_price = round($allowNegative ? $newTotalPrice : max(0, $newTotalPrice), 4);
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

    /**
     * تعديل متوسط تكلفة الوحدة دون تغيير الكمية: تحديث total_price وunit_price وتسجيل حركة تقييم (كمية 0).
     *
     * @return array{value_delta: float, category: Category}
     */
    public function applyPureCostRevaluation(
        Category $category,
        float $newAverageUnitCost,
        ?string $referenceType = 'manual_average_cost',
        ?int $referenceId = null,
        ?string $reason = null,
        ?string $performedBy = null,
    ): array {
        $newAverageUnitCost = max(0.0, (float) $newAverageUnitCost);

        $cat = Category::query()->lockForUpdate()->findOrFail($category->id);
        $qty = (float) ($cat->quantity ?? 0);
        $oldTp = (float) ($cat->total_price ?? 0);

        if ($qty < 0.0000001) {
            $cat->total_price = 0;
            $cat->unit_price = $newAverageUnitCost;
            $cat->save();
            $this->refreshMirrorBalances((int) $cat->id);

            return [
                'value_delta' => 0.0,
                'category' => $cat->fresh(),
            ];
        }

        $newTp = round($qty * $newAverageUnitCost, 4);
        $delta = round($newTp - $oldTp, 4);

        $cat->total_price = max(0.0, $newTp);
        if (($cat->warehouse ?? '') !== 'مخزن منتج تام') {
            $cat->save();
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $cat->id);
            $cat->refresh();
        } else {
            $cat->unit_price = $qty > 0.0000001 ? round(((float) $cat->total_price) / $qty, 6) : $newAverageUnitCost;
            $cat->save();
        }

        if (abs($delta) > 0.00001) {
            $stock = $cat->stock_id ? Stock::query()->find($cat->stock_id) : null;
            $direction = $delta >= 0 ? 'in' : 'out';
            $movementType = $delta >= 0 ? InventoryMovementType::AdjustmentGain : InventoryMovementType::AdjustmentLoss;

            InventoryMovement::query()->create([
                'category_id' => $cat->id,
                'stock_id' => $stock?->id,
                'warehouse_name' => $stock?->name ?? (string) $cat->warehouse,
                'direction' => $direction,
                'movement_type' => $movementType->value,
                'quantity' => 0,
                'unit_cost' => round(abs($delta) / $qty, 4),
                'total_cost' => round(abs($delta), 4),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId ?? $cat->id,
                'reason' => $reason ?? 'تعديل متوسط تكلفة الوحدة (بدون تغيير كمية)',
                'performed_by' => $performedBy ?? $this->defaultActor(),
            ]);
        }

        $this->refreshMirrorBalances((int) $cat->id);

        return [
            'value_delta' => $delta,
            'category' => $cat->fresh(),
        ];
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
