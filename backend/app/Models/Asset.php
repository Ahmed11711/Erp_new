<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'asset_date',
        'purchase_date',
        'payment_amount',
        'bank_id',
        'asset_amount',
        'purchase_price',
        'current_value',
        'scrap_value',
        'life_span',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
    ];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

    public function assetAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'asset_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'depreciation_account_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'expense_account_id');
    }

}
