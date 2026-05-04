<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrder extends Model
{
    protected $fillable = [
        'recipe_id',
        'output_product_id',
        'quantity',
        'status',
        'materials_cost_total',
        'additional_costs_total',
        'total_output_cost',
        'started_at',
        'completed_at',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'materials_cost_total' => 'decimal:4',
        'additional_costs_total' => 'decimal:4',
        'total_output_cost' => 'decimal:4',
        'status' => ProductionOrderStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
