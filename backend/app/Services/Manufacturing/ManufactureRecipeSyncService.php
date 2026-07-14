<?php

namespace App\Services\Manufacturing;

use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
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

        $this->syncLegacyManufactureLines($anchorId, $products);

        RecipeStructureValidator::assertValidForRecipe(
            $recipe->fresh(['ingredients.item']),
            $anchorId
        );
    }

    /**
     * Keep legacy manufacture_products rows aligned with recipe ingredient decimals.
     * Creates the Manufacture row when missing so confirmation / consumption can use it.
     *
     * @param  array<int, array{id:mixed, quantity:mixed, total_price:mixed}>  $products
     */
    public function syncLegacyManufactureLines(int $anchorId, array $products): void
    {
        $total = 0.0;
        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $total += (float) ($product['total_price'] ?? 0);
        }

        $manufacture = Manufacture::query()->where('product_id', $anchorId)->first();
        if ($manufacture === null) {
            $manufacture = Manufacture::create([
                'product_id' => $anchorId,
                'total' => round($total, 2),
            ]);
        } else {
            $manufacture->total = round($total, 2);
            $manufacture->save();
        }

        foreach ($products as $product) {
            $qty = (float) ($product['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $totalPrice = (float) ($product['total_price'] ?? 0);
            ManufactureProduct::query()->updateOrCreate(
                [
                    'manufacture_id' => $manufacture->id,
                    'product_id' => (int) $product['id'],
                ],
                [
                    'quantity' => $qty,
                    'total_price' => $totalPrice,
                ]
            );
        }
    }

    /**
     * Materialize / refresh legacy Manufacture (+ BOM lines) from a Recipe.
     * No-op when the recipe has no output product or no usable ingredients.
     */
    public function syncLegacyManufactureLinesFromRecipe(Recipe $recipe): void
    {
        $outputItemId = (int) ($recipe->output_item_id ?? 0);
        if ($outputItemId <= 0) {
            return;
        }

        // Keep Manufacture on the recipe's actual output SKU (including color variants).
        // Do not collapse to parent — many BOMs are owned by the variant itself.
        $recipe->loadMissing('ingredients');
        $products = [];
        foreach ($recipe->ingredients as $ingredient) {
            $qty = (float) ($ingredient->quantity ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $unitCost = (float) ($ingredient->unit_cost ?? 0);
            $products[] = [
                'id' => (int) $ingredient->item_id,
                'quantity' => $qty,
                'total_price' => round($qty * $unitCost, 4),
            ];
        }

        if ($products !== []) {
            $this->syncLegacyManufactureLines($outputItemId, $products);
        }
    }

    /**
     * Ensure a legacy Manufacture exists for confirmation when only a Recipe is present.
     */
    public function ensureLegacyManufactureForProduct(int $productId): bool
    {
        $item = Item::query()->find($productId);
        if ($item === null) {
            return false;
        }

        if (Manufacture::query()->where('product_id', $productId)->exists()) {
            return true;
        }

        // Color variants often own their Recipe/BOM directly.
        $recipe = Recipe::query()->where('output_item_id', $productId)->first();
        if ($recipe === null && $item->recipe_id) {
            $recipe = Recipe::query()->find((int) $item->recipe_id);
        }

        if ($recipe === null) {
            $anchorId = ManufacturingConsumptionResolver::outputAnchorId($item);
            if ($anchorId !== $productId && Manufacture::query()->where('product_id', $anchorId)->exists()) {
                return true;
            }
            $recipe = Recipe::query()->where('output_item_id', $anchorId)->first();
            if ($recipe === null) {
                return false;
            }
        }

        if (! $recipe->output_item_id) {
            $recipe->output_item_id = $productId;
            $recipe->save();
        }

        $this->syncLegacyManufactureLinesFromRecipe($recipe->fresh(['ingredients']));

        $outputId = (int) ($recipe->output_item_id ?: $productId);

        return Manufacture::query()->where('product_id', $outputId)->exists()
            || Manufacture::query()->where('product_id', $productId)->exists();
    }
}
