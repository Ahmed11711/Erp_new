<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Models\ProcessingDispatchNote;
use App\Models\ProcessingInvoice;
use App\Models\ProcessingOrder;
use App\Models\ProcessingReceipt;
use App\Models\Supplier;
use App\Models\TransactionType;
use App\Services\Documents\DocumentNumberService;
use App\Services\Processing\ProcessingDispatchService;
use App\Services\Processing\ProcessingInvoiceService;
use App\Services\Processing\ProcessingOrderService;
use App\Services\Processing\ProcessingReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessingOrderController extends Controller
{
    public function __construct(
        private ProcessingOrderService $orders,
        private DocumentNumberService $numbers,
    ) {
    }

    public function index(Request $request)
    {
        $q = ProcessingOrder::query()
            ->with([
                'supplier:id,supplier_name',
                'sourceStock:id,name',
                'destinationStock:id,name',
                'dispatchNotes:id,processing_order_id,dispatch_number,dispatch_date',
            ])
            ->withCount('lines')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', (int) $request->supplier_id);
        }

        if ($request->filled('q')) {
            $raw = trim((string) $request->q);
            $term = '%' . $raw . '%';
            $q->where(function ($w) use ($term, $raw) {
                $w->where('order_number', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->where('supplier_name', 'like', $term))
                    ->orWhereHas('dispatchNotes', fn ($d) => $d->where('dispatch_number', 'like', $term));
                if (ctype_digit($raw)) {
                    $w->orWhere('id', (int) $raw);
                }
            });
        }

        return response()->json($q->paginate((int) ($request->itemsPerPage ?? 15)));
    }

    public function show(int $id)
    {
        $order = ProcessingOrder::query()
            ->with([
                'supplier',
                'sourceStock',
                'destinationStock',
                'lines.category.measurement',
                'lines.atVendorCategory',
                'lines.destinationCategory',
                'dispatchNotes.lines',
                'dispatchNotes.shippingCompany:id,name,type',
                'receipts.lines.destinationCategory.measurement',
                'receipts.lines.category',
                'invoices.lines',
                'materialBalances',
            ])
            ->findOrFail($id);

        return response()->json($this->orders->enrichForDisplay($order));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'source_stock_id' => 'nullable|exists:stocks,id',
            'destination_stock_id' => 'nullable|exists:stocks,id',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'expected_service_total' => 'nullable|numeric|min:0',
            'lines' => 'required|array|min:1',
            'lines.*.category_id' => 'required|exists:categories,id',
            'lines.*.ordered_qty' => 'required|numeric|min:0.000001',
            'lines.*.expected_service_amount' => 'nullable|numeric|min:0',
            'lines.*.notes' => 'nullable|string',
        ]);

        try {
            $order = $this->orders->create($data);

            return response()->json($order, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, int $id)
    {
        $order = ProcessingOrder::query()->findOrFail($id);
        $data = $request->validate([
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'source_stock_id' => 'nullable|exists:stocks,id',
            'destination_stock_id' => 'nullable|exists:stocks,id',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'expected_service_total' => 'nullable|numeric|min:0',
            'lines' => 'sometimes|array|min:1',
            'lines.*.category_id' => 'required_with:lines|exists:categories,id',
            'lines.*.ordered_qty' => 'required_with:lines|numeric|min:0.000001',
            'lines.*.expected_service_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $order = $this->orders->update($order, $data);

            return response()->json($order);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function approve(int $id)
    {
        $order = ProcessingOrder::query()->findOrFail($id);
        try {
            return response()->json($this->orders->approve($order));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function vendors(Request $request)
    {
        $q = Supplier::query()
            ->select('id', 'supplier_name', 'supplier_phone', 'supplier_address', 'balance', 'supplier_type')
            ->with('supplierType:id,supplier_type')
            ->orderBy('supplier_name');

        if ($request->filled('q')) {
            $term = '%' . trim((string) $request->q) . '%';
            $q->where('supplier_name', 'like', $term);
        }

        return response()->json($q->get());
    }

    public function meta()
    {
        $nextDispatchNumber = null;
        $dispatchType = TransactionType::query()->where('code', 'PROCESSING_DISPATCH')->first();
        if ($dispatchType) {
            try {
                $nextDispatchNumber = $this->numbers->peek((int) $dispatchType->id);
            } catch (\Throwable) {
                $nextDispatchNumber = null;
            }
        }

        return response()->json([
            'next_dispatch_number' => $nextDispatchNumber,
            'dispatch_types' => [
                ['value' => 'goods_to_supplier', 'label' => 'صرف بضاعة لمورد'],
                ['value' => 'amanat', 'label' => 'صرف أمانات'],
                ['value' => 'custody', 'label' => 'صرف عهدة'],
            ],
            'line_types' => [
                ['value' => 'printing', 'label' => 'طباعة'],
                ['value' => 'packaging', 'label' => 'تغليف'],
                ['value' => 'cutting', 'label' => 'تقطيع'],
                ['value' => 'labeling', 'label' => 'لصق / ملصقات'],
                ['value' => 'transport', 'label' => 'نقل'],
                ['value' => 'other', 'label' => 'أخرى'],
            ],
            'order_statuses' => [
                'draft' => 'مسودة',
                'approved' => 'معتمد',
                'in_progress' => 'قيد التشغيل',
                'partially_received' => 'استلام جزئي',
                'completed' => 'مكتمل',
                'cancelled' => 'ملغى',
            ],
        ]);
    }
}
