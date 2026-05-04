<?php

namespace App\Services\Manufacturing;

use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Enums\ProductionOrderStatus;
use App\Exceptions\Manufacturing\EmptyRecipeException;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

final class ProductionOrderLifecycleService
{
    public function __construct(
        private InventoryMovementLedgerService $ledger,
        private ProductionOrderGlService $gl,
    ) {
    }

    public function createDraft(int $recipeId, int $outputProductId, string $quantity, ?string $notes = null, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($recipeId, $outputProductId, $quantity, $notes, $userId) {
            $recipe = Recipe::query()->with('ingredients')->findOrFail($recipeId);
            if ($recipe->ingredients->isEmpty()) {
                throw new EmptyRecipeException('Cannot create a production order: recipe has no ingredient lines.');
            }
            if ((int) ($recipe->output_item_id ?? 0) !== (int) $outputProductId) {
                throw new \InvalidArgumentException(
                    'Output product must match the recipe output (recipe.output_item_id must equal output_product_id).'
                );
            }

            RecipeStructureValidator::assertValidForRecipe($recipe, $outputProductId);

            return ProductionOrder::query()->create([
                'recipe_id' => $recipe->id,
                'output_product_id' => $outputProductId,
                'quantity' => $quantity,
                'status' => ProductionOrderStatus::Draft,
                'notes' => $notes,
                'user_id' => $userId,
            ]);
        });
    }

    public function start(ProductionOrder $order, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== ProductionOrderStatus::Draft) {
                throw new \RuntimeException('Only draft production orders can be started.');
            }

            $recipe = Recipe::query()->with(['ingredients.item', 'extraCosts'])->findOrFail($order->recipe_id);
            if ($recipe->ingredients->isEmpty()) {
                throw new EmptyRecipeException('Cannot start production: recipe has no ingredient lines.');
            }
            $batchQty = (float) $order->quantity;

            $materialsTotal = 0.0;
            $glLines = [];

            foreach ($recipe->ingredients as $ing) {
                $item = $ing->item;
                if (! $item) {
                    continue;
                }

                $need = (float) $ing->quantity * $batchQty;
                if ($need <= 0) {
                    continue;
                }

                $cat = Item::query()->lockForUpdate()->findOrFail($item->id);
                $currentQty = (float) ($cat->quantity ?? 0);
                if ($currentQty + 1e-9 < $need) {
                    throw new \RuntimeException(
                        'Insufficient stock for ' . $cat->category_name . ' (need ' . $need . ', have ' . $currentQty . ').'
                    );
                }

                $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $cat->id);
                $lineCost = $need * $avg;
                $materialsTotal += $lineCost;

                $this->ledger->recordOutbound(
                    $cat,
                    InventoryMovementType::ProductionOrderMaterialIssue,
                    $need,
                    $avg,
                    $lineCost,
                    true,
                    'production_order',
                    (int) $order->id,
                    'أمر إنتاج — صرف مواد',
                    null,
                    null,
                );

                $glLines[] = ['category_id' => (int) $cat->id, 'amount' => round($lineCost, 4)];
            }

            $extras = 0.0;
            foreach ($recipe->extraCosts as $ec) {
                $extras += $ec->resolvedAmount($materialsTotal);
            }

            $order->materials_cost_total = round($materialsTotal, 4);
            $order->additional_costs_total = round($extras, 4);
            $order->status = ProductionOrderStatus::InProgress;
            $order->started_at = now();
            if ($userId !== null) {
                $order->user_id = $userId;
            }
            $order->save();

            $this->gl->postMaterialConsumption((int) $order->id, $glLines, $userId ?? (auth()->check() ? auth()->id() : null));

            return $order->fresh();
        });
    }

    public function complete(ProductionOrder $order, ?int $userId = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== ProductionOrderStatus::InProgress) {
                throw new \RuntimeException('Only in-progress production orders can be completed.');
            }

            $output = Item::query()->lockForUpdate()->findOrFail($order->output_product_id);
            $outType = $output->resolvedProductType();
            if (! in_array($outType, [ProductType::SemiFinished, ProductType::Finished], true)) {
                throw new \RuntimeException('Invalid output product type for production.');
            }

            $qty = (float) $order->quantity;
            $materials = (float) ($order->materials_cost_total ?? 0);
            $extras = (float) ($order->additional_costs_total ?? 0);
            $totalCost = round($materials + $extras, 4);
            $unitCost = $qty > 0 ? $totalCost / $qty : 0.0;

            $expectedStock = $outType === ProductType::Finished
                ? ProductionWarehouseResolver::finishedGoodsStock()
                : ProductionWarehouseResolver::wipStock();

            if ($expectedStock) {
                $stockOk = (int) ($output->stock_id ?? 0) === (int) $expectedStock->id
                    || trim((string) ($output->warehouse ?? '')) === trim((string) ($expectedStock->name ?? ''));
                if (! $stockOk) {
                    throw new \RuntimeException(
                        'Output item must be stored in the correct warehouse for its product type ('
                        . ($outType === ProductType::Finished ? 'finished goods' : 'WIP')
                        . '). Align stock_id / warehouse with the standard stock row.'
                    );
                }
            }

            $this->ledger->recordInbound(
                $output,
                InventoryMovementType::ProductionOrderOutput,
                $qty,
                $unitCost,
                $totalCost,
                true,
                'production_order',
                (int) $order->id,
                'أمر إنتاج — استلام منتج',
                null,
                null,
            );

            if ($outType === ProductType::Finished) {
                $this->gl->postCompletionToFinishedGoods(
                    (int) $order->id,
                    $totalCost,
                    ProductionWarehouseResolver::finishedGoodsStock(),
                    $userId ?? (auth()->check() ? auth()->id() : null),
                );
            }

            $order->total_output_cost = $totalCost;
            $order->status = ProductionOrderStatus::Completed;
            $order->completed_at = now();
            if ($userId !== null) {
                $order->user_id = $userId;
            }
            $order->save();

            return $order->fresh();
        });
    }

    public function cancelDraft(ProductionOrder $order): ProductionOrder
    {
        return DB::transaction(function () use ($order) {
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status !== ProductionOrderStatus::Draft) {
                throw new \RuntimeException('Only draft production orders can be cancelled.');
            }
            $order->status = ProductionOrderStatus::Cancelled;
            $order->save();

            return $order;
        });
    }
}
