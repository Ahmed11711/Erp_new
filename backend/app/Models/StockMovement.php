<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'category_id',
        'warehouse_stock_id',
        'warehouse_name',
        'direction',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'reason',
        'performed_by',
    ];

    protected $casts = [
        'quantity'   => 'decimal:6',
        'unit_cost'  => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'warehouse_stock_id');
    }
}
