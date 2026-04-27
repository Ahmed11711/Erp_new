<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'recipe_name',
        'description',
    ];

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function extraCosts(): HasMany
    {
        return $this->hasMany(RecipeExtraCost::class);
    }

    public function itemsUsingRecipe()
    {
        return $this->hasMany(Item::class, 'recipe_id');
    }
}
