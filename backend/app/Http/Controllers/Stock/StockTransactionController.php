<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockTransactionRequest;
use App\Models\StockTransaction;
use App\Models\TransactionType;
use App\Services\Stock\StockTransactionPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * JSON API for inventory documents (صرف / إضافة / إرجاع / نقل).
 */
class StockTransactionController extends Controller
{
    public function typesIndex()
    {
        $rows = TransactionType::query()
            ->where('is_active', true)
            ->where('code', '!=', 'PURCHASE_ADD')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'prefix', 'stock_direction', 'affects_stock']);

        return response()->json($rows, 200);
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->query('itemsPerPage') ?: 15);
        $q = StockTransaction::query()
            ->with(['type:id,name,code,prefix', 'warehouse:id,name'])
            ->orderByDesc('id');

        if ($request->filled('transaction_type_id')) {
            $q->where('transaction_type_id', (int) $request->transaction_type_id);
        }
        if ($request->filled('warehouse_id')) {
            $q->where('warehouse_id', (int) $request->warehouse_id);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('document_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('document_date', '<=', $request->date_to);
        }
        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->q).'%';
            $q->where(function ($sub) use ($term) {
                $sub->where('transaction_no', 'like', $term)
                    ->orWhere('notes', 'like', $term);
            });
        }

        return response()->json($q->paginate($perPage), 200);
    }

    public function store(StoreStockTransactionRequest $request, StockTransactionPostingService $posting)
    {
        try {
            $doc = $posting->post($request->validated());

            return response()->json(['success' => true, 'transaction' => $doc], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id)
    {
        $doc = StockTransaction::query()
            ->with(['items.product:id,category_name,unit_price', 'items.toProduct:id,category_name', 'type', 'warehouse:id,name', 'creator:id,name'])
            ->findOrFail($id);

        return response()->json([
            'transaction' => $doc,
            'print_url' => URL::temporarySignedRoute(
                'documents.stock-transactions.print',
                now()->addHours(48),
                ['stockTransaction' => $doc->id]
            ),
        ], 200);
    }
}
