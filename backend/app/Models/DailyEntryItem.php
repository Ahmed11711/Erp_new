<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyEntryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_entry_id',
        'account_id',
        'debit',
        'credit',
        'notes',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function dailyEntry()
    {
        return $this->belongsTo(DailyEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(TreeAccount::class, 'account_id');
    }
}

