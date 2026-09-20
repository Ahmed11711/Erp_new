<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    protected $fillable = [
        'settlement_id',
        'order_id',
        'shipping_company_detail_id',
        'order_amount',
        'collected_amount',
        'settled_amount',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'order_amount' => 'decimal:3',
        'collected_amount' => 'decimal:3',
        'settled_amount' => 'decimal:3',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingCompanyDetail(): BelongsTo
    {
        return $this->belongsTo(shippingCompanyDetails::class, 'shipping_company_detail_id');
    }
}
