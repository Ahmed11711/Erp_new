<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Services\Items\CostCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipeExtraCostController extends Controller
{
    public function __construct(
        private CostCalculationService $costService,
    ) {
    }

    /**
     * List all extra costs for a recipe, with cost breakdown.
     */
    public function index(int $recipeId): JsonResponse
    {
        $recipe = Recipe::with(['ingredients', 'extraCosts'])->findOrFail($recipeId);

        $breakdown = $this->costService->calculateFinalCost(
            $recipe->ingredients,
            $recipe->extraCosts,
        );

        return response()->json([
            'extra_costs' => $recipe->extraCosts,
            'breakdown'   => $breakdown,
        ]);
    }

    /**
     * Add a new extra cost line to a recipe.
     */
    public function store(Request $request, int $recipeId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'type'  => ['required', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $extra = $recipe->extraCosts()->create($data);

        return response()->json([
            'extra_cost' => $extra,
            'breakdown'  => $this->freshBreakdown($recipe),
        ], 201);
    }

    /**
     * Update an existing extra cost line.
     */
    public function update(Request $request, int $recipeId, int $extraCostId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);

        $extra = RecipeExtraCost::where('recipe_id', $recipe->id)
            ->findOrFail($extraCostId);

        $data = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'type'  => ['sometimes', 'in:fixed,percentage'],
            'value' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $extra->update($data);

        return response()->json([
            'extra_cost' => $extra->fresh(),
            'breakdown'  => $this->freshBreakdown($recipe),
        ]);
    }

    /**
     * Delete an extra cost line.
     */
    public function destroy(int $recipeId, int $extraCostId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);

        $extra = RecipeExtraCost::where('recipe_id', $recipe->id)
            ->findOrFail($extraCostId);

        $extra->delete();

        return response()->json([
            'message'   => 'تم حذف التكلفة الإضافية.',
            'breakdown' => $this->freshBreakdown($recipe),
        ]);
    }

    /**
     * Return full cost breakdown (for real-time recalculation on the frontend).
     */
    public function breakdown(int $recipeId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);

        $breakdown = $this->costService->breakdownForRecipe($recipe);

        return response()->json($breakdown);
    }

    private function freshBreakdown(Recipe $recipe): array
    {
        $recipe->load(['ingredients', 'extraCosts']);

        return $this->costService->calculateFinalCost(
            $recipe->ingredients,
            $recipe->extraCosts,
        );
    }
}
