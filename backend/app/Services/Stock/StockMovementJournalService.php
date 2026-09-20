<?php

namespace App\Services\Stock;

use App\Models\Category;
use App\Models\Stock;
use App\Models\StockMovement;

/**
 * Writes rows into stock_movements (extends legacy schema with before/after snapshots).
 */
class StockMovementJournalService
{
    /**
     * @param  'in'|'out'  $direction
     */
    public function record(
        int $categoryId,
        ?int $warehouseStockId,
        string $direction,
        float $qtyAbsolute,
        string $movementTypeCode,
        ?float $beforeQty,
        ?float $afterQty,
        ?string $referenceType,
        ?int $referenceId,
        ?int $stockTransactionId = null,
        ?float $unitCost = null,
        ?float $totalCost = null,
        ?string $reason = null,
    ): StockMovement {
        $warehouseName = '';
        if ($warehouseStockId) {
            $warehouseName = (string) Stock::query()->where('id', $warehouseStockId)->value('name');
        }
        if ($warehouseName === '') {
            $warehouseName = (string) Category::query()->where('id', $categoryId)->value('warehouse');
        }

        return StockMovement::query()->create([
            'category_id' => $categoryId,
            'warehouse_stock_id' => $warehouseStockId,
            'warehouse_name' => $warehouseName !== '' ? $warehouseName : '—',
            'direction' => $direction,
            'movement_type' => $movementTypeCode,
            'quantity' => $qtyAbsolute,
            'before_qty' => $beforeQty,
            'after_qty' => $afterQty,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'stock_transaction_id' => $stockTransactionId,
            'reason' => $reason ?? $movementTypeCode,
            'performed_by' => auth()->check() ? auth()->user()->name : null,
        ]);
    }
}
