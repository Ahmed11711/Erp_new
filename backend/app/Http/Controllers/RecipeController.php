<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use App\Models\StockMovement;
use App\Services\Items\CostCalculationService;
use App\Services\Items\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function __construct(
        private CostCalculationService $costService,
        private InventoryService $inventoryService,
    ) {
    }

    public function index(): JsonResponse
    {
        $rows = Recipe::query()
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
        $recipe = Recipe::with(['ingredients.item', 'extraCosts'])->findOrFail($id);

        $breakdown = $this->costService->breakdownForRecipe($recipe);

        return response()->json([
            'recipe'    => $recipe,
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
            'ingredients'              => ['required', 'array', 'min:1'],
            'ingredients.*.item_id'    => ['required', 'integer', 'exists:categories,id'],
            'ingredients.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'ingredients.*.unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'extra_costs'              => ['nullable', 'array'],
            'extra_costs.*.name'       => ['required', 'string', 'max:255'],
            'extra_costs.*.type'       => ['required', 'in:fixed,percentage'],
            'extra_costs.*.value'      => ['required', 'numeric', 'min:0'],
        ]);

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

            return $recipe;
        });

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
        $recipe = Recipe::findOrFail($id);

        $data = $request->validate([
            'recipe_name'              => ['sometimes', 'string', 'max:255'],
            'description'              => ['nullable', 'string'],
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

        DB::transaction(function () use ($recipe, $data) {
            if (isset($data['recipe_name'])) {
                $recipe->recipe_name = $data['recipe_name'];
            }
            if (array_key_exists('description', $data)) {
                $recipe->description = $data['description'];
            }
            $recipe->save();

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
        });

        $recipe->load(['ingredients.item', 'extraCosts']);
        $breakdown = $this->costService->breakdownForRecipe($recipe);

        return response()->json([
            'message'   => 'تم تحديث الوصفة بنجاح.',
            'recipe'    => $recipe,
            'breakdown' => $breakdown,
        ]);
    }

    /**
     * Delete a recipe and all related data.
     */
    public function destroy(int $id): JsonResponse
    {
        $recipe = Recipe::findOrFail($id);

        DB::transaction(function () use ($recipe) {
            StockMovement::where('reference_type', 'recipe')
                ->where('reference_id', $recipe->id)
                ->delete();

            RecipeExtraCost::where('recipe_id', $recipe->id)->delete();
            RecipeIngredient::where('recipe_id', $recipe->id)->delete();

            DB::table('categories')->where('recipe_id', $recipe->id)->update(['recipe_id' => null]);

            $recipe->delete();
        });

        return response()->json(['message' => 'تم حذف الوصفة وجميع البيانات المرتبطة بها.']);
    }

    /**
     * Bulk delete multiple recipes.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:recipes,id'],
        ]);

        $ids = $data['ids'];
        $count = 0;

        DB::transaction(function () use ($ids, &$count) {
            StockMovement::where('reference_type', 'recipe')
                ->whereIn('reference_id', $ids)
                ->delete();

            RecipeExtraCost::whereIn('recipe_id', $ids)->delete();
            RecipeIngredient::whereIn('recipe_id', $ids)->delete();

            DB::table('categories')->whereIn('recipe_id', $ids)->update(['recipe_id' => null]);

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
        $recipe   = Recipe::findOrFail($id);
        $batchQty = (int) $request->input('batch_qty', 1);

        $result = $this->inventoryService->checkStockAvailability($recipe, $batchQty);

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
}
