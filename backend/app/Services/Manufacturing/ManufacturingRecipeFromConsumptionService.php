<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductionOrderStatus;
use App\Exceptions\Manufacturing\ProductAlreadyCompletedException;
use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\ProductionOrder;
use App\Models\Recipe;

/**
 * Persists confirmation consumption lines back to Recipe + Manufacture BOM.
 */
final class ManufacturingRecipeFromConsumptionService
{
    public function __construct(
        private ManufactureRecipeSyncService $recipeSync,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $lines  Resolved consumption lines (after overrides).
     */
    public function updateFromConsumptionLines(int $outputProductId, float $batchQty, array $lines): void
    {
        if ($batchQty <= 0.0000001) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون أكبر من صفر.');
        }

        if ($lines === []) {
            throw new \InvalidArgumentException('لا توجد مواد لتحديث الوصفة.');
        }

        $outputItem = Item::query()->findOrFail($outputProductId);
        $anchorId = ManufacturingConsumptionResolver::outputAnchorId($outputItem);

        $recipe = Recipe::query()->where('output_item_id', $anchorId)->first();
        if ($recipe && $this->recipeLockedByCompletedProduction((int) $recipe->id)) {
            throw new ProductAlreadyCompletedException(
                'لا يمكن تعديل الوصفة: وُجد أمر إنتاج مكتمل مسبقاً لهذا المنتج.'
            );
        }

        $products = $this->buildRecipeProducts($lines, $batchQty);
        if ($products === []) {
            throw new \InvalidArgumentException('لا توجد مواد صالحة لتحديث الوصفة.');
        }

        $this->recipeSync->sync($anchorId, $products);
        $this->replaceManufactureProducts($anchorId, $products);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{id:int, quantity:float, total_price:float}>
     */
    private function buildRecipeProducts(array $lines, float $batchQty): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $perUnit = (float) ($line['quantity'] ?? 0) / $batchQty;
            if ($perUnit <= 0.0000001) {
                continue;
            }

            $resolvedId = (int) ($line['resolved_category_id'] ?? 0);
            if ($resolvedId <= 0) {
                continue;
            }

            $ingredientId = $this->ingredientAnchorId($resolvedId);
            $unitCost = (float) ($line['unit_cost'] ?? 0);
            $lineTotal = round($perUnit * $unitCost, 4);

            if (isset($merged[$ingredientId])) {
                $merged[$ingredientId]['quantity'] += $perUnit;
                $merged[$ingredientId]['total_price'] += $lineTotal;
            } else {
                $merged[$ingredientId] = [
                    'id' => $ingredientId,
                    'quantity' => $perUnit,
                    'total_price' => $lineTotal,
                ];
            }
        }

        $products = [];
        foreach ($merged as $row) {
            $products[] = [
                'id' => (int) $row['id'],
                'quantity' => round((float) $row['quantity'], 6),
                'total_price' => round((float) $row['total_price'], 4),
            ];
        }

        return $products;
    }

    private function ingredientAnchorId(int $categoryId): int
    {
        $item = Item::query()->find($categoryId);
        if (! $item) {
            return $categoryId;
        }

        if ($item->parent_item_id) {
            return (int) $item->parent_item_id;
        }

        return (int) $item->id;
    }

    /**
     * @param  list<array{id:int, quantity:float, total_price:float}>  $products
     */
    private function replaceManufactureProducts(int $anchorId, array $products): void
    {
        $manufacture = Manufacture::query()->where('product_id', $anchorId)->first();
        if (! $manufacture) {
            return;
        }

        ManufactureProduct::query()->where('manufacture_id', $manufacture->id)->delete();

        $total = 0.0;
        foreach ($products as $product) {
            ManufactureProduct::create([
                'manufacture_id' => $manufacture->id,
                'product_id' => (int) $product['id'],
                'quantity' => (float) $product['quantity'],
                'total_price' => (float) $product['total_price'],
            ]);
            $total += (float) $product['total_price'];
        }

        $manufacture->total = round($total, 4);
        $manufacture->save();
    }

    private function recipeLockedByCompletedProduction(int $recipeId): bool
    {
        return ProductionOrder::query()
            ->where('recipe_id', $recipeId)
            ->where('status', ProductionOrderStatus::Completed)
            ->exists();
    }
}
