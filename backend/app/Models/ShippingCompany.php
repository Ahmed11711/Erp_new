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
    ];

    public function treeAccount()
    {
        return $this->belongsTo(TreeAccount::class, 'tree_account_id');
    }
}
