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

    /** ذمم أصول: مستحق من الشركة (تحصيل/شحن) — منفصل عن tree_account_id لذمم مصروف الشحن */
    public function receivableTreeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'receivable_tree_account_id');
    }

    public function details()
    {
        return $this->hasMany(shippingCompanyDetails::class, 'shipping_company_id');
    }
}
