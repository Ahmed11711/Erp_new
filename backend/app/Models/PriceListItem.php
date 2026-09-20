<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListItem extends Model
{
    protected $fillable = [
        'price_list_id',
        'code',
        'product_name',
        'price',
        'currency',
        'photo1',
        'photo2',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'sort_order' => 'integer',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}
