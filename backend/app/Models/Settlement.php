<?php

namespace App\Models;

use App\Enums\SettlementBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $fillable = [
        'settlement_number',
        'provider_type',
        'provider_id',
        'status',
        'period_from',
        'period_to',
        'total_collected',
        'total_settled',
        'remaining_balance',
        'settled_at',
        'created_by_user_id',
        'posted_by_user_id',
        'notes',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'settled_at' => 'datetime',
        'total_collected' => 'decimal:3',
        'total_settled' => 'decimal:3',
        'remaining_balance' => 'decimal:3',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->status === SettlementBatchStatus::Draft->value;
    }
}
