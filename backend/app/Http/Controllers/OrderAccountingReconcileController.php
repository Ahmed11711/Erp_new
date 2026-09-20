<?php

namespace App\Http\Controllers;

use App\Services\Accounting\OrdersAccountingReconcileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderAccountingReconcileController extends Controller
{
    private const MAX_RANGE_DAYS = 731;

    public function __construct(
        private OrdersAccountingReconcileService $reconcileService
    ) {
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        if (OrdersAccountingReconcileService::maxRangeDaysExceeded($validated['date_from'], $validated['date_to'], self::MAX_RANGE_DAYS)) {
            return response()->json([
                'message' => 'نطاق التاريخ كبير جداً (الحدّ حوالي سنتان). اضغط تقسيم النطاق.',
            ], 422);
        }

        $counts = $this->reconcileService->countInRange($validated['date_from'], $validated['date_to']);

        return response()->json([
            'orders_count' => $counts['active'],
            'total_in_range' => $counts['total'],
            'excluded_count' => $counts['excluded'],
            'by_status' => $counts['by_status'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'max_orders_per_run' => OrdersAccountingReconcileService::MAX_ORDERS_PER_RUN,
            'would_exceed_limit' => $counts['active'] > OrdersAccountingReconcileService::MAX_ORDERS_PER_RUN,
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'rebuild_prepaid' => ['sometimes', 'boolean'],
        ]);

        if (OrdersAccountingReconcileService::maxRangeDaysExceeded($validated['date_from'], $validated['date_to'], self::MAX_RANGE_DAYS)) {
            return response()->json([
                'message' => 'نطاق التاريخ كبير جداً (الحدّ حوالي سنتان).',
            ], 422);
        }

        $rebuildPrepaid = (bool) ($validated['rebuild_prepaid'] ?? false);

        $result = $this->reconcileService->reconcileRange(
            $validated['date_from'],
            $validated['date_to'],
            $rebuildPrepaid
        );

        if (($result['success'] ?? false) === false) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}
