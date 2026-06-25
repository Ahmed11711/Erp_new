<?php

namespace App\Http\Controllers;

use App\Enums\OrderRollbackTarget;
use App\Models\Order;
use App\Services\Orders\OrderRollbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderRollbackController extends Controller
{
    public function __construct(
        private OrderRollbackService $rollbackService,
    ) {
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $order = Order::with('order_details')->find($id);
        if (! $order) {
            return response()->json(['message' => 'not found'], 404);
        }

        if (! $this->rollbackService->isEligible($order)) {
            return response()->json([
                'message' => 'لا يمكن إعادة فتح الطلب من الحالة: ' . $order->order_status,
            ], 422);
        }

        $target = $this->resolveTarget($request->input('target', 'confirmed'));
        if (! $this->canRollback()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->rollbackService->preview($order, $target));
    }

    public function execute(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'target' => 'required|in:confirmed,new',
            'reason' => 'required|string|min:3|max:2000',
            'confirmed' => 'required|boolean|accepted',
        ]);

        $order = Order::with('order_details')->find($id);
        if (! $order) {
            return response()->json(['message' => 'not found'], 404);
        }

        $target = $this->resolveTarget($request->input('target'));
        if (! $this->canRollback()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $result = $this->rollbackService->execute(
                $order,
                $target,
                (string) $request->input('reason'),
                (int) auth()->id(),
            );

            return response()->json($result, 200);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function resolveTarget(?string $value): OrderRollbackTarget
    {
        return match ($value) {
            'new' => OrderRollbackTarget::New,
            default => OrderRollbackTarget::Confirmed,
        };
    }

    private function canRollback(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return has_permission('orders.change_status')
            || has_permission('orders.assign_driver')
            || has_permission('system.rbac')
            || in_array(trim((string) ($user->department ?? '')), [
                'Admin',
                'Operation Management',
                'Operation Specialist',
                'Logistics Specialist',
                'Shipping Management',
            ], true);
    }
}
