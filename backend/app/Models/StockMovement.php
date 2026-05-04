<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated Prefer {@see InventoryMovement}. Table: inventory_movements (warehouse_stock_id aliases stock_id).
 */
class StockMovement extends InventoryMovement
{
    protected $fillable = [
        'category_id',
        'stock_id',
        'warehouse_stock_id',
        'warehouse_name',
        'direction',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'daily_entry_id',
        'reason',
        'performed_by',
    ];

    public function setWarehouseStockIdAttribute(?int $value): void
    {
        $this->attributes['stock_id'] = $value;
    }

    public function getWarehouseStockIdAttribute(): ?int
    {
        return isset($this->attributes['stock_id']) ? (int) $this->attributes['stock_id'] : null;
    }

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }
}
