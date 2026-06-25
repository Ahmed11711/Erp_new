<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'orders_count',
        'refused_orders_percentage',
        'tree_account_id',
        'receivable_tree_account_id',
    ];

    public function treeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'tree_account_id');
    }

    /** ذمم مدين (أصول): تحصيل COD — ما على المندوب للشركة */
    public function receivableTreeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'receivable_tree_account_id');
    }

    public function details()
    {
        return $this->hasMany(shippingCompanyDetails::class, 'shipping_company_id');
    }

    /**
     * صافي المركز: موجب = المندوب مدين للشركة (تحصيل) | سالب = للمندوب مستحق على الشركة.
     */
    public function displayBalance(): float
    {
        $receivable = 0.0;
        $payable = 0.0;
        $hasLedger = false;

        if ($this->receivable_tree_account_id) {
            if (! $this->relationLoaded('receivableTreeAccount')) {
                $this->load('receivableTreeAccount:id,balance');
            }
            if ($this->receivableTreeAccount) {
                $receivable = (float) $this->receivableTreeAccount->balance;
                $hasLedger = true;
            }
        }

        if ($this->tree_account_id) {
            if (! $this->relationLoaded('treeAccount')) {
                $this->load('treeAccount:id,balance');
            }
            if ($this->treeAccount) {
                $payable = (float) $this->treeAccount->balance;
                $hasLedger = true;
            }
        }

        if ($hasLedger) {
            return round($receivable + $payable, 2);
        }

        return (float) ($this->attributes['balance'] ?? 0);
    }
}
