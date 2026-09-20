<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use App\Models\StockMovement;
use App\Models\ProductionOrder;
use App\Enums\ProductionOrderStatus;
use App\Exceptions\Manufacturing\ProductAlreadyCompletedException;
use App\Services\Manufacturing\ManufactureRecipeSyncService;
use App\Services\Manufacturing\RecipeStructureValidator;
use App\Services\Items\CostCalculationService;
use App\Services\Items\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    /**
     * قائمة المواد (BOM) في الواجهة تُحمَّل من GET /manufacture — يجب مسحها مع حذف الوصفة لنفس المنتج.
     *
     * @param  iterable<int, int|null>  $outputItemIds
     */
    private function deleteLegacyManufacturesForOutputProducts(iterable $outputItemIds): void
    {
        $ids = collect($outputItemIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();
        foreach ($ids as $productId) {
            $manufactureIds = Manufacture::query()->where('product_id', $productId)->pluck('id');
            if ($manufactureIds->isEmpty()) {
                continue;
            }
            ManufactureProduct::query()->whereIn('manufacture_id', $manufactureIds)->delete();
            Manufacture::query()->whereIn('id', $manufactureIds)->delete();
        }
    }

    public function __construct(
        private CostCalculationService $costService,
        private InventoryService $inventoryService,
        private ManufactureRecipeSyncService $manufactureRecipeSync,
    ) {
    }

    public function index(): JsonResponse
    {
        $rows = Recipe::query()
            ->with(['outputItem:id,category_name,product_type,color,item_code'])
            ->withCount(['ingredients', 'extraCosts'])
            ->orderBy('recipe_name')
            ->get();

        return response()->json($rows, 200);
    }

    /**
     * Full detail of a single recipe: ingredients, extra costs, cost breakdown.
     */
    public function show(int $id): JsonResponse
    {
        $recipe = Recipe::with([
            'ingredients.item:id,category_name,item_code,color,warehouse',
            'extraCosts',
            'outputItem:id,category_name,item_code,color,warehouse,category_price,unit_price',
        ])->findOrFail($id);

        $breakdown = $this->costService->breakdownForRecipe($recipe);
        $recipePayload = $recipe->toArray();
        $recipePayload['bom_locked'] = $this->recipeLockedByCompletedProduction((int) $recipe->id);

        return response()->json([
            'recipe'    => $recipePayload,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Create a new recipe with ingredients and optional extra costs.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipe_name'              => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'output_item_id'           => ['nullable', 'integer', 'exists:categories,id'],
            'ingredients'              => ['required', 'array', 'min:1'],
            'ingredients.*.item_id'    => ['required', 'integer', 'exists:categories,id'],
            'ingredients.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'extra_costs'              => ['nullable', 'array'],
            'extra_costs.*.name'       => ['required', 'string', 'max:255'],
            'extra_costs.*.type'       => ['required', 'in:fixed,percentage'],
            'extra_costs.*.value'      => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $recipe = DB::transaction(function () use ($data) {
                $recipe = Recipe::create([
                    'recipe_name' => $data['recipe_name'],
                    'description' => $data['description'] ?? null,
                ]);

                foreach ($data['ingredients'] as $ing) {
                    RecipeIngredient::create([
                        'recipe_id' => $recipe->id,
                        'item_id'   => $ing['item_id'],
                        'quantity'  => $ing['quantity'],
                        'unit_cost' => $ing['unit_cost'] ?? null,
                    ]);
                }

                foreach (($data['extra_costs'] ?? []) as $ec) {
                    RecipeExtraCost::create([
                        'recipe_id' => $recipe->id,
                        'name'      => $ec['name'],
                        'type'      => $ec['type'],
                        'value'     => $ec['value'],
                    ]);
                }

                if (! empty($data['output_item_id'])) {
                    $outputId = (int) $data['output_item_id'];
                    if (Recipe::query()->where('output_item_id', $outputId)->exists()) {
                        throw new \InvalidArgumentException('Another recipe already owns this output product.');
                    }
                    $recipe->output_item_id = $outputId;
                    $recipe->save();
                    Item::query()->whereKey($outputId)->update(['recipe_id' => $recipe->id]);
                    RecipeStructureValidator::assertValidForRecipe(
                        $recipe->fresh(['ingredients.item']),
                        $outputId
                    );
                    $this->manufactureRecipeSync->syncLegacyManufactureLinesFromRecipe(
                        $recipe->fresh(['ingredients'])
                    );
                }

                return $recipe;
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $recipe->load(['ingredients.item', 'extraCosts']);
        $breakdown = $this->costService->breakdownForRecipe($recipe);

        return response()->json([
            'message'   => 'تم إنشاء الوصفة بنجاح.',
            'recipe'    => $recipe,
            'breakdown' => $breakdown,
        ], 201);
    }

    /**
     * Update recipe: name, ingredients, and extra costs.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (
            ! has_permission('manufacturing.edit_recipe')
            && ! has_permission('categories.manage')
            && ! has_permission('system.rbac')
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $recipe = Recipe::findOrFail($id);

        $data = $request->validate([
            'recipe_name'              => ['sometimes', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
            'output_item_id'           => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'ingredients'              => ['sometimes', 'array', 'min:1'],
            'ingredients.*.item_id'    => ['required', 'integer', 'exists:categories,id'],
            'ingredients.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'extra_costs'              => ['sometimes', 'array'],
            'extra_costs.*.id'         => ['nullable', 'integer'],
            'extra_costs.*.name'       => ['required', 'string', 'max:255'],
            'extra_costs.*.type'       => ['required', 'in:fixed,percentage'],
            'extra_costs.*.value'      => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $mutatesBom = isset($data['ingredients']) || isset($data['extra_costs']) || array_key_exists('output_item_id', $data);
            if ($mutatesBom && $this->recipeLockedByCompletedProduction((int) $recipe->id)) {
                throw new ProductAlreadyCompletedException(
                    'Recipe cannot be changed: a production order has already completed for this recipe.'
                );
            }

            DB::transaction(function () use ($recipe, $data) {
                if (isset($data['recipe_name'])) {
                    $recipe->recipe_name = $data['recipe_name'];
                }
                if (array_key_exists('description', $data)) {
                    $recipe->description = $data['description'];
                }
                $recipe->save();

                if (array_key_exists('output_item_id', $data)) {
                    $newOutput = $data['output_item_id'] !== null ? (int) $data['output_item_id'] : null;
                    $prevOutput = $recipe->output_item_id ? (int) $recipe->output_item_id : null;
                    if ($prevOutput && $prevOutput !== $newOutput) {
                        Item::query()->whereKey($prevOutput)->update(['recipe_id' => null]);
                    }
                    if ($newOutput) {
                        $dup = Recipe::query()
                            ->where('output_item_id', $newOutput)
                            ->where('id', '!=', $recipe->id)
                            ->exists();
                        if ($dup) {
                            throw new \InvalidArgumentException('Another recipe already owns this output product.');
                        }
                        $recipe->output_item_id = $newOutput;
                        $recipe->save();
                        Item::query()->whereKey($newOutput)->update(['recipe_id' => $recipe->id]);
                    } else {
                        $recipe->output_item_id = null;
                        $recipe->save();
                    }
                }

                if (isset($data['ingredients'])) {
                    RecipeIngredient::where('recipe_id', $recipe->id)->delete();
                    foreach ($data['ingredients'] as $ing) {
                        RecipeIngredient::create([
                            'recipe_id' => $recipe->id,
                            'item_id'   => $ing['item_id'],
                            'quantity'  => $ing['quantity'],
                            'unit_cost' => $ing['unit_cost'] ?? null,
                        ]);
                    }
                }

                if (isset($data['extra_costs'])) {
                    RecipeExtraCost::where('recipe_id', $recipe->id)->delete();
                    foreach ($data['extra_costs'] as $ec) {
                        RecipeExtraCost::create([
                            'recipe_id' => $recipe->id,
                            'name'      => $ec['name'],
                            'type'      => $ec['type'],
                            'value'     => $ec['value'],
                        ]);
                    }
                }

                $outputId = $recipe->fresh()->output_item_id;
                if ($outputId) {
                    RecipeStructureValidator::assertValidForRecipe(
                        $recipe->fresh(['ingredients.item']),
                        (int) $outputId
                    );
                }

                if (isset($data['ingredients'])) {
                    $this->manufactureRecipeSync->syncLegacyManufactureLinesFromRecipe(
                        $recipe->fresh(['ingredients'])
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $recipe->load(['ingredients.item', 'extraCosts']);
        $breakdown = $this->costService->breakdownForRecipe($recipe);

        return response()->json([
            'message'   => 'تم تحديث الوصفة بنجاح.',
            'recipe'    => $recipe,
            'breakdown' => $breakdown,
        ]);
    }

    private function canDeleteRecipe(): bool
    {
        return has_permission('manufacturing.delete_recipe') || has_permission('system.rbac');
    }

    /**
     * Delete a recipe and all related data.
     */
    public function destroy(int $id): JsonResponse
    {
        if (! $this->canDeleteRecipe()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $recipe = Recipe::findOrFail($id);

        try {
            if ($this->recipeLockedByCompletedProduction((int) $recipe->id)) {
                throw new ProductAlreadyCompletedException(
                    'Cannot delete recipe: a production order has already completed for this recipe.'
                );
            }

            DB::transaction(function () use ($recipe) {
                StockMovement::where('reference_type', 'recipe')
                    ->where('reference_id', $recipe->id)
                    ->delete();

                RecipeExtraCost::where('recipe_id', $recipe->id)->delete();
                RecipeIngredient::where('recipe_id', $recipe->id)->delete();

                DB::table('categories')->where('recipe_id', $recipe->id)->update(['recipe_id' => null]);
                if ($recipe->output_item_id) {
                    Item::query()->whereKey((int) $recipe->output_item_id)->update(['recipe_id' => null]);
                    $this->deleteLegacyManufacturesForOutputProducts([(int) $recipe->output_item_id]);
                }

                $recipe->delete();
            });

            return response()->json(['message' => 'تم حذف الوصفة وجميع البيانات المرتبطة بها.']);
        } catch (ProductAlreadyCompletedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Bulk delete multiple recipes.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        if (! $this->canDeleteRecipe()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:recipes,id'],
        ]);

        $ids = $data['ids'];
        $count = 0;

        $outputItemIdsForManufactures = Recipe::query()
            ->whereIn('id', $ids)
            ->pluck('output_item_id');

        DB::transaction(function () use ($ids, &$count, $outputItemIdsForManufactures) {
            StockMovement::where('reference_type', 'recipe')
                ->whereIn('reference_id', $ids)
                ->delete();

            RecipeExtraCost::whereIn('recipe_id', $ids)->delete();
            RecipeIngredient::whereIn('recipe_id', $ids)->delete();

            DB::table('categories')->whereIn('recipe_id', $ids)->update(['recipe_id' => null]);
            foreach (Recipe::query()->whereIn('id', $ids)->get(['id', 'output_item_id']) as $r) {
                if ($r->output_item_id) {
                    Item::query()->whereKey((int) $r->output_item_id)->update(['recipe_id' => null]);
                }
            }

            $this->deleteLegacyManufacturesForOutputProducts($outputItemIdsForManufactures);

            $count = Recipe::whereIn('id', $ids)->delete();
        });

        return response()->json([
            'message' => "تم حذف {$count} وصفة بنجاح.",
            'deleted' => $count,
        ]);
    }

    /**
     * Pre-flight stock check before executing a recipe.
     */
    public function checkStock(int $id, Request $request): JsonResponse
    {
        $recipe = Recipe::findOrFail($id);
        $batchQty = (int) $request->input('batch_qty', 1);
        $finishedItemId = (int) $request->input('finished_item_id', 0);
        $prodColorId = null;
        if ($finishedItemId > 0) {
            $raw = Item::query()->whereKey($finishedItemId)->value('color_id');
            $prodColorId = $raw !== null ? (int) $raw : null;
        }

        $result = $this->inventoryService->checkStockAvailability($recipe, $batchQty, $prodColorId);

        return response()->json($result);
    }

    /**
     * Execute a recipe: deduct raw materials, add finished product.
     */
    public function execute(int $id, Request $request): JsonResponse
    {
        $data = $request->validate([
            'finished_item_id' => ['required', 'integer', 'exists:categories,id'],
            'batch_qty'        => ['sometimes', 'integer', 'min:1'],
        ]);

        $recipe   = Recipe::findOrFail($id);
        $batchQty = (int) ($data['batch_qty'] ?? 1);
        $user     = auth()->check() ? auth()->user()->name : 'النظام';

        try {
            $result = $this->inventoryService->executeRecipe(
                $recipe,
                (int) $data['finished_item_id'],
                $batchQty,
                $user,
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type'    => 'insufficient_stock',
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type'    => 'validation_error',
            ], 422);
        }

        return response()->json([
            'message'  => 'تم تنفيذ الوصفة بنجاح.',
            'produced' => $result['produced'],
            'deducted' => $result['deducted'],
            'movements_count' => count($result['movements']),
        ]);
    }

    /**
     * Get stock movements history for a recipe.
     */
    public function movements(int $id): JsonResponse
    {
        $recipe = Recipe::findOrFail($id);

        $movements = $this->inventoryService->movementsForReference('recipe', $recipe->id);

        return response()->json(['movements' => $movements]);
    }

    private function recipeLockedByCompletedProduction(int $recipeId): bool
    {
        return ProductionOrder::query()
            ->where('recipe_id', $recipeId)
            ->where('status', ProductionOrderStatus::Completed)
            ->exists();
    }
}
