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
     *
     * ذمة COD تُحدَّث تشغيلياً عبر shipping_company_procedure (عمود balance).
     * حساب tree_account_id يعكس مستحقات الشركة للمندوب (إن وُجد).
     */
    public function displayBalance(): float
    {
        $operationalReceivable = round((float) ($this->attributes['balance'] ?? 0), 2);

        $payable = 0.0;
        if ($this->tree_account_id) {
            if (! $this->relationLoaded('treeAccount')) {
                $this->load('treeAccount:id,balance');
            }
            if ($this->treeAccount) {
                $payable = round((float) $this->treeAccount->balance, 2);
            }
        }

        return round($operationalReceivable + $payable, 2);
    }
}
