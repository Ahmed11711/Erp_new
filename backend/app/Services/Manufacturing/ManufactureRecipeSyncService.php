<?php

namespace App\Services\Manufacturing;

use App\Models\Item;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;

/**
 * يطابق سجل Recipe مع الوصفة اليدوية (Manufacture) لتظهر في GET /recipes وقوائم الوصفات.
 */
final class ManufactureRecipeSyncService
{
    /**
     * @param  array<int, array{id:mixed, quantity:mixed, total_price:mixed}>  $products
     * @param  array<int, array{name:string,type:string,value:numeric}>  $extraCosts
     */
    public function sync(int $productId, array $products, array $extraCosts = []): void
    {
        DB::transaction(function () use ($productId, $products, $extraCosts) {
            $this->syncWithinTransaction($productId, $products, $extraCosts);
        });
    }

    /**
     * @param  array<int, array{id:mixed, quantity:mixed, total_price:mixed}>  $products
     * @param  array<int, array{name:string,type:string,value:numeric}>  $extraCosts
     */
    private function syncWithinTransaction(int $productId, array $products, array $extraCosts): void
    {
        $outputItem = Item::query()->findOrFail($productId);

        $recipe = Recipe::query()->where('output_item_id', $productId)->first();
        if ($recipe === null) {
            $recipe = Recipe::create([
                'recipe_name' => (string) ($outputItem->category_name ?? 'Recipe #'.$productId),
                'description' => null,
                'output_item_id' => $productId,
            ]);
        } else {
            $recipe->recipe_name = (string) ($outputItem->category_name ?? $recipe->recipe_name);
            $recipe->save();
        }

        RecipeIngredient::query()->where('recipe_id', $recipe->id)->delete();
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 0);
            $totalPrice = (float) ($product['total_price'] ?? 0);
            $unitCost = $qty > 0.000001 ? round($totalPrice / $qty, 6) : 0.0;
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'item_id' => (int) $product['id'],
                'quantity' => $qty,
                'unit_cost' => $unitCost,
            ]);
        }

        RecipeExtraCost::query()->where('recipe_id', $recipe->id)->delete();
        foreach ($extraCosts as $ec) {
            RecipeExtraCost::create([
                'recipe_id' => $recipe->id,
                'name' => $ec['name'],
                'type' => $ec['type'],
                'value' => $ec['value'],
            ]);
        }

        Item::query()->whereKey($productId)->update(['recipe_id' => $recipe->id]);

        RecipeStructureValidator::assertValidForRecipe(
            $recipe->fresh(['ingredients.item']),
            $productId
        );
    }
}
