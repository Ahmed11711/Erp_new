<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryCostHistory extends Model
{
    protected $fillable = [
        'category_id',
        'source_type',
        'source_id',
        'source_line_id',
        'apply_mode',
        'old_qty',
        'old_unit_cost',
        'old_total_cost',
        'receipt_qty',
        'receipt_unit_cost',
        'receipt_total_cost',
        'new_qty',
        'new_unit_cost',
        'new_total_cost',
        'waste_qty',
        'waste_cost',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'old_qty' => 'float',
        'old_unit_cost' => 'float',
        'old_total_cost' => 'float',
        'receipt_qty' => 'float',
        'receipt_unit_cost' => 'float',
        'receipt_total_cost' => 'float',
        'new_qty' => 'float',
        'new_unit_cost' => 'float',
        'new_total_cost' => 'float',
        'waste_qty' => 'float',
        'waste_cost' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
