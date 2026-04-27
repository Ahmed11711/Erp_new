<?php

namespace App\Services\Items;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Handles all warehouse stock operations for recipe execution:
 *
 * 1. Deducting raw materials from Raw Material Warehouse.
 * 2. Adding finished product to Finished Goods Warehouse.
 * 3. Logging every movement in stock_movements.
 *
 * All operations run inside a single DB transaction.
 */
class InventoryService
{
    public const WAREHOUSE_RAW      = 'مخزن مواد خام';
    public const WAREHOUSE_FINISHED = 'مخزن منتج تام';

    /**
     * Execute a recipe: deduct ingredients, add finished product.
     *
     * @param  Recipe   $recipe         The recipe to execute
     * @param  int      $finishedItemId The category_id of the finished good
     * @param  int      $batchQty       How many units of the finished product to produce (default 1)
     * @param  string   $performedBy    Name of the user performing this
     * @return array{movements: StockMovement[], deducted: array, produced: array}
     *
     * @throws \RuntimeException  When insufficient stock for any raw material
     * @throws \InvalidArgumentException  When ingredient references are invalid
     */
    public function executeRecipe(
        Recipe $recipe,
        int $finishedItemId,
        int $batchQty = 1,
        string $performedBy = 'النظام',
    ): array {
        $recipe->loadMissing(['ingredients', 'extraCosts']);

        $this->validateBeforeExecution($recipe, $finishedItemId, $batchQty);

        return DB::transaction(function () use ($recipe, $finishedItemId, $batchQty, $performedBy) {
            $movements = [];
            $deducted  = [];

            foreach ($recipe->ingredients as $ingredient) {
                $requiredQty = bcmul((string) $ingredient->quantity, (string) $batchQty, 6);

                $rawItem = Category::lockForUpdate()->find($ingredient->item_id);

                if (! $rawItem) {
                    throw new \InvalidArgumentException(
                        "Ingredient item #{$ingredient->item_id} not found in categories."
                    );
                }

                $currentQty = (string) ($rawItem->quantity ?? '0');

                if (bccomp($currentQty, $requiredQty, 6) < 0) {
                    throw new \RuntimeException(
                        "Insufficient stock for '{$rawItem->category_name}' (ID {$rawItem->id}). "
                        ."Required: {$requiredQty}, Available: {$currentQty}."
                    );
                }

                $newQty = bcsub($currentQty, $requiredQty, 6);
                $unitCost   = (string) ($ingredient->unit_cost ?? $rawItem->category_price ?? '0');
                $totalCost  = bcmul($requiredQty, $unitCost, 4);

                $rawItem->quantity    = $newQty;
                $rawItem->total_price = bcmul($newQty, (string) ($rawItem->unit_price ?: $rawItem->category_price ?: '0'), 4);
                $rawItem->save();

                $movement = StockMovement::create([
                    'category_id'        => $rawItem->id,
                    'warehouse_stock_id' => $rawItem->stock_id,
                    'warehouse_name'     => $rawItem->warehouse ?? self::WAREHOUSE_RAW,
                    'direction'          => 'out',
                    'quantity'           => $requiredQty,
                    'unit_cost'          => $unitCost,
                    'total_cost'         => $totalCost,
                    'reference_type'     => 'recipe',
                    'reference_id'       => $recipe->id,
                    'reason'             => "Recipe execution: {$recipe->recipe_name}",
                    'performed_by'       => $performedBy,
                ]);

                $movements[] = $movement;
                $deducted[]  = [
                    'item_id'   => $rawItem->id,
                    'item_name' => $rawItem->category_name,
                    'quantity'  => $requiredQty,
                    'unit_cost' => $unitCost,
                ];
            }

            $finishedItem = Category::lockForUpdate()->findOrFail($finishedItemId);
            $costService  = app(CostCalculationService::class);
            $breakdown    = $costService->calculateFinalCost($recipe->ingredients, $recipe->extraCosts);
            $unitFinalCost = bcmul($breakdown['final_cost'], (string) $batchQty, 4);

            $prevQty = (string) ($finishedItem->quantity ?? '0');
            $newQty  = bcadd($prevQty, (string) $batchQty, 6);

            $finishedItem->quantity         = $newQty;
            $finishedItem->sell_total_price = bcmul($newQty, (string) ($finishedItem->category_price ?: '0'), 4);
            $finishedItem->save();

            $inMovement = StockMovement::create([
                'category_id'        => $finishedItem->id,
                'warehouse_stock_id' => $finishedItem->stock_id,
                'warehouse_name'     => $finishedItem->warehouse ?? self::WAREHOUSE_FINISHED,
                'direction'          => 'in',
                'quantity'           => $batchQty,
                'unit_cost'          => $breakdown['final_cost'],
                'total_cost'         => $unitFinalCost,
                'reference_type'     => 'recipe',
                'reference_id'       => $recipe->id,
                'reason'             => "Finished goods from recipe: {$recipe->recipe_name}",
                'performed_by'       => $performedBy,
            ]);

            $movements[] = $inMovement;

            return [
                'movements' => $movements,
                'deducted'  => $deducted,
                'produced'  => [
                    'item_id'    => $finishedItem->id,
                    'item_name'  => $finishedItem->category_name,
                    'quantity'   => $batchQty,
                    'final_cost' => $unitFinalCost,
                ],
            ];
        });
    }

    /**
     * Pre-flight checks before executing a recipe.
     *
     * @throws \InvalidArgumentException
     */
    private function validateBeforeExecution(Recipe $recipe, int $finishedItemId, int $batchQty): void
    {
        if ($batchQty < 1) {
            throw new \InvalidArgumentException('Batch quantity must be at least 1.');
        }

        if ($recipe->ingredients->isEmpty()) {
            throw new \InvalidArgumentException('Recipe has no ingredients.');
        }

        if (! Category::where('id', $finishedItemId)->exists()) {
            throw new \InvalidArgumentException("Finished goods item #{$finishedItemId} does not exist.");
        }

        foreach ($recipe->ingredients as $ing) {
            if ((float) $ing->quantity <= 0) {
                throw new \InvalidArgumentException(
                    "Ingredient item #{$ing->item_id} has invalid quantity ({$ing->quantity})."
                );
            }
        }
    }

    /**
     * Check whether all raw materials have sufficient stock for a given batch size.
     *
     * @return array{sufficient: bool, shortages: array<int, array{item_id:int, item_name:string, required:string, available:string}>}
     */
    public function checkStockAvailability(Recipe $recipe, int $batchQty = 1): array
    {
        $recipe->loadMissing('ingredients');

        $shortages = [];

        foreach ($recipe->ingredients as $ing) {
            $requiredQty = bcmul((string) $ing->quantity, (string) $batchQty, 6);
            $item = Category::find($ing->item_id);

            if (! $item) {
                $shortages[] = [
                    'item_id'   => $ing->item_id,
                    'item_name' => '(not found)',
                    'required'  => $requiredQty,
                    'available' => '0',
                ];
                continue;
            }

            $available = (string) ($item->quantity ?? '0');

            if (bccomp($available, $requiredQty, 6) < 0) {
                $shortages[] = [
                    'item_id'   => $item->id,
                    'item_name' => $item->category_name,
                    'required'  => $requiredQty,
                    'available' => $available,
                ];
            }
        }

        return [
            'sufficient' => count($shortages) === 0,
            'shortages'  => $shortages,
        ];
    }

    /**
     * Return all movements for a given reference (e.g. recipe execution).
     */
    public function movementsForReference(string $referenceType, int $referenceId): \Illuminate\Database\Eloquent\Collection
    {
        return StockMovement::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->orderBy('created_at')
            ->get();
    }
}
