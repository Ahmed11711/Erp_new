<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCountImport;
use App\Services\Inventory\StockCountReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCountImportController extends Controller
{
    public function __construct(
        private StockCountReconciliationService $reconciliation
    ) {}

    /**
     * Step 1: Upload Excel and get preview analysis.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:51200',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $originalName = $file->getClientOriginalName();

        try {
            $import = $this->reconciliation->preview($path, $originalName, auth()->id());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل تحليل الملف: ' . $e->getMessage(),
            ], 422);
        }

        $rows = $import->rows()
            ->where('is_duplicate_row', false)
            ->orderBy('excel_row_number')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'row_number' => $row->excel_row_number,
                'product_name' => $row->excel_product_name,
                'excel_warehouse' => $row->excel_warehouse_name,
                'excel_quantity' => (float) $row->excel_quantity,
                'excel_unit_cost' => $row->excel_unit_cost !== null ? (float) $row->excel_unit_cost : null,
                'match_type' => $row->match_type,
                'match_confidence' => $row->match_confidence ? (float) $row->match_confidence : null,
                'matched_name' => $row->matched_name,
                'matched_category_id' => $row->matched_category_id,
                'system_quantity' => $row->system_quantity !== null ? (float) $row->system_quantity : null,
                'quantity_difference' => $row->quantity_difference !== null ? (float) $row->quantity_difference : null,
                'adjustment_direction' => $row->adjustment_direction,
                'assigned_warehouse' => $row->assigned_warehouse_name,
                'is_new_product' => $row->is_new_product,
                'classification_method' => $row->classification_method,
                'warnings' => $row->warnings ?? [],
            ]);

        return response()->json([
            'import_token' => $import->import_token,
            'expires_in_minutes' => 120,
            'filename' => $import->filename,
            'summary' => $import->summary,
            'warnings' => $import->warnings ?? [],
            'rows' => $rows,
            'message' => 'تم تحليل الملف بنجاح. راجع البيانات ثم اضغط تأكيد للتطبيق.',
        ]);
    }

    /**
     * Step 2: Confirm and apply the import.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'import_token' => 'required|string|max:64',
        ]);

        try {
            $import = $this->reconciliation->confirm(
                $request->input('import_token'),
                auth()->id()
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل تطبيق الاستيراد: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'تم تطبيق الجرد بنجاح.',
            'summary' => $import->summary,
            'import_id' => $import->id,
        ]);
    }

    /**
     * Cancel a pending import.
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'import_token' => 'required|string|max:64',
        ]);

        try {
            $this->reconciliation->cancel($request->input('import_token'));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'فشل الإلغاء: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json(['message' => 'تم إلغاء جلسة الاستيراد.']);
    }

    /**
     * Get import history.
     */
    public function history(Request $request): JsonResponse
    {
        $imports = InventoryCountImport::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'filename' => $i->filename,
                'status' => $i->status,
                'total_rows' => $i->total_rows,
                'matched_rows' => $i->matched_rows,
                'new_items_rows' => $i->new_items_rows,
                'adjusted_rows' => $i->adjusted_rows,
                'summary' => $i->summary,
                'created_at' => $i->created_at?->toIso8601String(),
                'confirmed_at' => $i->confirmed_at?->toIso8601String(),
            ]);

        return response()->json(['imports' => $imports]);
    }

    /**
     * Get details for a specific import.
     */
    public function show(int $id): JsonResponse
    {
        $import = InventoryCountImport::with('rows')->findOrFail($id);

        $rows = $import->rows
            ->where('is_duplicate_row', false)
            ->sortBy('excel_row_number')
            ->values()
            ->map(fn ($row) => [
                'id' => $row->id,
                'row_number' => $row->excel_row_number,
                'product_name' => $row->excel_product_name,
                'excel_warehouse' => $row->excel_warehouse_name,
                'excel_quantity' => (float) $row->excel_quantity,
                'match_type' => $row->match_type,
                'match_confidence' => $row->match_confidence ? (float) $row->match_confidence : null,
                'matched_name' => $row->matched_name,
                'system_quantity' => $row->system_quantity !== null ? (float) $row->system_quantity : null,
                'quantity_difference' => $row->quantity_difference !== null ? (float) $row->quantity_difference : null,
                'adjustment_direction' => $row->adjustment_direction,
                'assigned_warehouse' => $row->assigned_warehouse_name,
                'is_new_product' => $row->is_new_product,
                'row_status' => $row->row_status,
                'error_message' => $row->error_message,
                'warnings' => $row->warnings ?? [],
            ]);

        return response()->json([
            'import' => [
                'id' => $import->id,
                'filename' => $import->filename,
                'status' => $import->status,
                'summary' => $import->summary,
                'created_at' => $import->created_at?->toIso8601String(),
                'confirmed_at' => $import->confirmed_at?->toIso8601String(),
            ],
            'rows' => $rows,
        ]);
    }
}
