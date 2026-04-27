<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeExtraCost extends Model
{
    protected $fillable = [
        'recipe_id',
        'name',
        'type',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Resolve this line's monetary contribution.
     * Fixed → value as-is. Percentage → (value / 100) * materialsCost.
     */
    public function resolvedAmount(float $materialsCost): float
    {
        if ($this->type === 'percentage') {
            return round($materialsCost * ((float) $this->value / 100), 4);
        }

        return round((float) $this->value, 4);
    }
}
