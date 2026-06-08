<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy-compatible stock_movements row (+ ERP document audit columns).
 *
 * @property-read Category|null $category
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'category_id',
        'warehouse_stock_id',
        'warehouse_name',
        'direction',
        'movement_type',
        'quantity',
        'before_qty',
        'after_qty',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'stock_transaction_id',
        'reason',
        'performed_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'before_qty' => 'decimal:6',
        'after_qty' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'warehouse_stock_id');
    }

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }
}
