<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = [
        'category_id',
        'stock_id',
        'quantity',
        'cost_value',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'cost_value' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
