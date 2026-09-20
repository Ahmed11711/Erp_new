<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipmentLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
        'unit_cost' => 'float',
        'line_cogs' => 'float',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(OrderShipment::class, 'order_shipment_id');
    }

    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
