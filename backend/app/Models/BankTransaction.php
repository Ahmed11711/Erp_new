<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'type',
        'from_bank_id',
        'to_bank_id',
        'from_safe_id',
        'to_safe_id',
        'amount',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function fromBank()
    {
        return $this->belongsTo(Bank::class, 'from_bank_id');
    }

    public function toBank()
    {
        return $this->belongsTo(Bank::class, 'to_bank_id');
    }

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
}

