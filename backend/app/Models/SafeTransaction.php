<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'type',
        'from_safe_id',
        'to_safe_id',
        'counter_account_id',
        'amount',
        'notes',
        'entry_batch_code',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function fromSafe()
    {
        return $this->belongsTo(Safe::class, 'from_safe_id');
    }

    public function toSafe()
    {
        return $this->belongsTo(Safe::class, 'to_safe_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function counterAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'counter_account_id');
    }
}

