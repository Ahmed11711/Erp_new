<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRollbackAudit extends Model
{
    protected $fillable = [
        'order_id',
        'previous_status',
        'target_status',
        'reason',
        'performed_by',
        'reversed_accounting',
        'reversed_inventory',
        'reversed_operations',
    ];

    protected $casts = [
        'reversed_accounting' => 'array',
        'reversed_inventory' => 'array',
        'reversed_operations' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
