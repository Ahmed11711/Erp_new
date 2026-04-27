<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopifyProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'last_pushed_to_shopify_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
