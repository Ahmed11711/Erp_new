<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'tree_account_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function dailyEntry()
    {
        return $this->belongsTo(DailyEntry::class);
    }
}
