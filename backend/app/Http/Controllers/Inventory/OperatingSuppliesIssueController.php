<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\OperatingSuppliesIssueService;
use App\Services\Inventory\OperatingSuppliesWarehouseResolver;
use Illuminate\Http\Request;

class OperatingSuppliesIssueController extends Controller
{
    public function ensure()
    {
        $stock = OperatingSuppliesWarehouseResolver::ensureStock();
        $overheadId = OperatingSuppliesWarehouseResolver::ensureOverheadAccountId();

        return response()->json([
            'stock' => $stock->load('asset:id,name,code'),
            'overhead_account_id' => $overheadId,
        ]);
    }

    public function issue(Request $request, OperatingSuppliesIssueService $service)
    {
        $data = $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'qty' => 'required|numeric|min:0.000001',
            'production_id' => 'nullable|integer|exists:productions,id',
            'notes' => 'nullable|string|max:2000',
            'document_date' => 'nullable|date',
        ]);

        try {
            $doc = $service->issue($data);

            return response()->json([
                'success' => true,
                'message' => 'تم صرف مستلزمات التشغيل وترحيل القيد المحاسبي.',
                'transaction' => $doc,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
