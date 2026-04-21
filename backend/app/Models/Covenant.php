<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Covenant extends Model
{
    protected $fillable = [
        'transaction_date',
        'covenant_type',
        'holder_kind',
        'payment_type',
        'safe_id',
        'bank_id',
        'service_account_id',
        'amount',
        'description',
        'note',
        'user_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function safe(): BelongsTo
    {
        return $this->belongsTo(Safe::class, 'safe_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function serviceAccount(): BelongsTo
    {
        return $this->belongsTo(ServiceAccount::class, 'service_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
