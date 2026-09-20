<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Services\Manufacturing\ProductionOrderLifecycleService;
use App\Exceptions\Manufacturing\EmptyRecipeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function __construct(
        private ProductionOrderLifecycleService $lifecycle,
    ) {
    }

    public function index(): JsonResponse
    {
        $orders = ProductionOrder::query()
            ->with(['recipe:id,recipe_name,output_item_id', 'outputProduct:id,category_name,product_type,warehouse'])
            ->orderByDesc('id')
            ->paginate((int) request('itemsPerPage', 20));

        return response()->json($orders);
    }

    public function show(int $id): JsonResponse
    {
        $order = ProductionOrder::query()
            ->with([
                'recipe.ingredients.item',
                'recipe.extraCosts',
                'outputProduct',
                'user:id,name',
            ])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer', 'exists:recipes,id'],
            'output_product_id' => ['required', 'integer', 'exists:categories,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:512'],
        ]);

        try {
            $order = $this->lifecycle->createDraft(
                (int) $data['recipe_id'],
                (int) $data['output_product_id'],
                (string) $data['quantity'],
                $data['notes'] ?? null,
                auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (EmptyRecipeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order, 201);
    }

    public function start(int $id): JsonResponse
    {
        $order = ProductionOrder::query()->findOrFail($id);

        try {
            $order = $this->lifecycle->start($order, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    public function complete(int $id): JsonResponse
    {
        $order = ProductionOrder::query()->findOrFail($id);

        try {
            $order = $this->lifecycle->complete($order, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }

    public function cancel(int $id): JsonResponse
    {
        $order = ProductionOrder::query()->findOrFail($id);

        try {
            $order = $this->lifecycle->cancelDraft($order);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }
}
