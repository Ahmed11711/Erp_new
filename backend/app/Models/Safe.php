<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Safe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'balance',
        'type',
        'is_inside_branch',
        'branch_name',
        'account_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_inside_branch' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'account_id');
    }

    public function transactions()
    {
        return SafeTransaction::where(function ($q) {
            $q->where('from_safe_id', $this->id)
              ->orWhere('to_safe_id', $this->id);
        });
    }

    public function outgoingTransactions()
    {
        return $this->hasMany(SafeTransaction::class, 'from_safe_id');
    }

    public function incomingTransactions()
    {
        return $this->hasMany(SafeTransaction::class, 'to_safe_id');
    }
}

