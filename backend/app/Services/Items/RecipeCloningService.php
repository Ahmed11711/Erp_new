<?php

namespace App\Services\Items;

use App\Models\Recipe;
use App\Models\RecipeIngredient;

class RecipeCloningService
{
    public function duplicate(Recipe $source, ?string $newName = null): Recipe
    {
        $clone = new Recipe([
            'recipe_name' => $newName ?? ($source->recipe_name.' (نسخة)'),
            'description' => $source->description,
        ]);
        $clone->save();

        foreach ($source->ingredients as $row) {
            RecipeIngredient::query()->create([
                'recipe_id' => $clone->id,
                'item_id' => $row->item_id,
                'quantity' => $row->quantity,
                'unit_cost' => $row->unit_cost,
            ]);
        }

        return $clone->fresh(['ingredients']);
    }
}
