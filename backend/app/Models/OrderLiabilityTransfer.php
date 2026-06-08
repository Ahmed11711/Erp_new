<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLiabilityTransfer extends Model
{
    protected $fillable = [
        'order_id',
        'from_holder_type',
        'from_holder_id',
        'to_holder_type',
        'to_holder_id',
        'amount',
        'reason',
        'gl_batch_code',
        'performed_by_user_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'meta' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
