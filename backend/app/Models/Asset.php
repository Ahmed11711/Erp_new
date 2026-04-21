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
        'payment_source_type',
        'bank_id',
        'safe_id',
        'service_account_id',
        'asset_amount',
        'purchase_price',
        'current_value',
        'scrap_value',
        'life_span',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
        'last_depreciation_date',
    ];

    public function bank(){
        return $this->belongsTo(Bank::class);
    }

    public function safe()
    {
        return $this->belongsTo(Safe::class);
    }

    public function serviceAccount()
    {
        return $this->belongsTo(ServiceAccount::class);
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
