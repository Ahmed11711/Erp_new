<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransactionItem extends Model
{
    protected $fillable = [
        'stock_transaction_id',
        'product_id',
        'qty',
        'price',
        'total',
        'to_product_id',
        'qty_direction',
    ];

    protected $casts = [
        'qty' => 'decimal:6',
        'price' => 'decimal:6',
        'total' => 'decimal:6',
    ];

    public function stockTransaction(): BelongsTo
    {
        return $this->belongsTo(StockTransaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'product_id');
    }

    public function toProduct(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'to_product_id');
    }
}
