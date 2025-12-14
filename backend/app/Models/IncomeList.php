<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeList extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'sales',
        'sales_returns',
        'opening_raw_materials',
        'opening_under_processing',
        'opening_finished_goods',
        'opening_storage',
        'purchases',
        'purchase_expenses',
        'purchase_returns',
        'operating_expenses',
        'sales_expenses',
        'closing_raw_materials',
        'closing_under_processing',
        'closing_finished_goods',
        'last_storage',
        'total_cost_of_sales',
        'capital_gains',
        'other_revenues',
        'setup_expenses',
        'admin_expenses',
        'depreciation',
        'depreciation_reserves',
    ];

}
