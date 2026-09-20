<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
