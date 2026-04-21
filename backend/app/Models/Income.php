<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'date',
        'income_amount',
        'revenue_tree_account_id',
        'payment_type',
        'bank_id',
        'safe_id',
        'service_account_id',
    ];

    public function revenueTreeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'revenue_tree_account_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id');
    }

    public function safe()
    {
        return $this->belongsTo(Safe::class, 'safe_id');
    }

    public function serviceAccount()
    {
        return $this->belongsTo(ServiceAccount::class, 'service_account_id');
    }
}