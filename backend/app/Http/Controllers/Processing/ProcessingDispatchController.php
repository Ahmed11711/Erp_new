<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Models\ProcessingDispatchNote;
use App\Models\ProcessingOrder;
use App\Services\Processing\ProcessingDispatchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessingDispatchController extends Controller
{
    public function __construct(
        private ProcessingDispatchService $dispatchService,
    ) {
    }

    public function index(Request $request)
    {
        $q = ProcessingDispatchNote::query()
            ->with(['supplier:id,supplier_name', 'order:id,order_number'])
            ->orderByDesc('id');

        if ($request->filled('processing_order_id')) {
            $q->where('processing_order_id', (int) $request->processing_order_id);
        }

        return response()->json($q->paginate((int) ($request->itemsPerPage ?? 15)));
    }

    public function show(int $id)
    {
        $note = ProcessingDispatchNote::query()
            ->with(['supplier', 'order.lines.category', 'lines.category', 'lines.atVendorCategory', 'lines.orderLine'])
            ->findOrFail($id);

        return response()->json($note);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'processing_order_id' => 'required|exists:processing_orders,id',
            'dispatch_date' => 'nullable|date',
            'dispatch_type' => 'nullable|string|max:32',
            'notes' => 'nullable|string',
            'representative_type' => 'nullable|in:internal,external',
            'shipping_company_id' => [
                'nullable',
                Rule::exists('shipping_companies', 'id')->where('type', 'مندوب'),
            ],
            'external_representative_name' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.processing_order_line_id' => 'required|exists:processing_order_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.000001',
        ]);

        $order = ProcessingOrder::query()->findOrFail((int) $data['processing_order_id']);

        try {
            $note = $this->dispatchService->createDraft($order, $data);

            return response()->json($note, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function submitVoucher(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'dispatch_date' => 'nullable|date',
            'dispatch_type' => 'nullable|string|max:32',
            'expected_return_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'external_invoice_no' => 'nullable|string|max:128',
            'expected_service_total' => 'nullable|numeric|min:0',
            'representative_type' => 'nullable|in:internal,external',
            'shipping_company_id' => [
                'nullable',
                Rule::exists('shipping_companies', 'id')->where('type', 'مندوب'),
            ],
            'external_representative_name' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.category_id' => 'required|exists:categories,id',
            'lines.*.ordered_qty' => 'required|numeric|min:0.000001',
            'lines.*.expected_service_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->dispatchService->submitVoucher($data);

            return response()->json($result, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(int $id)
    {
        $note = ProcessingDispatchNote::query()->with('lines')->findOrFail($id);
        try {
            return response()->json($this->dispatchService->post($note));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
