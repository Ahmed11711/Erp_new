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
     * @param  array<int, array|object{name:mixed,type:mixed,value:mixed}>  $extraCosts
     */
    public function sync(int $productId, array $products, array $extraCosts = []): void
    {
        if (DB::transactionLevel() > 0) {
            $this->syncWithinTransaction($productId, $products, $extraCosts);

            return;
        }

        DB::transaction(function () use ($productId, $products, $extraCosts) {
            $this->syncWithinTransaction($productId, $products, $extraCosts);
        });
    }

    /**
     * Normalize a single extra-cost row from JSON/request (may be array or stdClass).
     *
     * @return array{name:string,type:string,value:float|string}|null
     */
    private function normalizeExtraCostRow(mixed $row): ?array
    {
        if ($row instanceof \JsonSerializable) {
            $row = $row->jsonSerialize();
        }
        $data = match (true) {
            is_array($row) => $row,
            is_object($row) => json_decode(json_encode($row), true),
            default => null,
        };
        if (! is_array($data)) {
            return null;
        }
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $type = isset($data['type']) ? (string) $data['type'] : '';
        $value = $data['value'] ?? null;
        if ($name === '' || ($type !== 'fixed' && $type !== 'percentage') || $value === null || $value === '') {
            return null;
        }

        return [
            'name' => $name,
            'type' => $type,
            'value' => is_numeric($value) ? $value + 0 : $value,
        ];
    }

    /**
     * @param  array<int, array{id:mixed, quantity:mixed, total_price:mixed}>  $products
     * @param  array<int, array|object{name:mixed,type:mixed,value:mixed}>  $extraCosts
     */
    private function syncWithinTransaction(int $productId, array $products, array $extraCosts): void
    {
        $outputItem = Item::query()->findOrFail($productId);
        $anchorId = ManufacturingConsumptionResolver::outputAnchorId($outputItem);
        $anchorItem = Item::query()->findOrFail($anchorId);

        $recipe = Recipe::query()->where('output_item_id', $anchorId)->first();
        if ($recipe === null) {
            $recipe = Recipe::create([
                'recipe_name' => (string) ($anchorItem->category_name ?? 'Recipe #'.$anchorId),
                'description' => null,
                'output_item_id' => $anchorId,
            ]);
        } else {
            $recipe->recipe_name = (string) ($anchorItem->category_name ?? $recipe->recipe_name);
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
            $line = $this->normalizeExtraCostRow($ec);
            if ($line === null) {
                continue;
            }
            RecipeExtraCost::create([
                'recipe_id' => $recipe->id,
                'name' => $line['name'],
                'type' => $line['type'],
                'value' => $line['value'],
            ]);
        }

        Item::query()->whereKey($anchorId)->update(['recipe_id' => $recipe->id]);

        RecipeStructureValidator::assertValidForRecipe(
            $recipe->fresh(['ingredients.item']),
            $anchorId
        );
    }
}
