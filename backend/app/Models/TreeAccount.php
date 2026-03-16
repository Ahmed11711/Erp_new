<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreeAccount extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'debit_balance' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'is_trading_account' => 'boolean',
        'detail_type' => 'string',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function mainAccount()
    {
        return $this->belongsTo(self::class, 'main_account_id');
    }

    public function accountEntries()
    {
        return $this->hasMany(AccountEntry::class, 'tree_account_id');
    }

    /**
     * Total balance = debit - credit (مطابق لأفضل أنظمة المحاسبة)
     */
    public function getTotalBalanceAttribute()
    {
        return (float) ($this->debit_balance ?? 0) - (float) ($this->credit_balance ?? 0);
    }
}