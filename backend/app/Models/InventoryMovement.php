<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $table = 'inventory_movements';

    protected $fillable = [
        'category_id',
        'stock_id',
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

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }

    public function dailyEntry(): BelongsTo
    {
        return $this->belongsTo(DailyEntry::class, 'daily_entry_id');
    }
}
