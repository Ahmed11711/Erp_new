<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderConfirmationSession extends Model
{
    protected $fillable = ['customer_phone', 'order_id', 'flow_state'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
