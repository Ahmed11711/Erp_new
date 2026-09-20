<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Models\ProcessingInvoice;
use App\Services\Processing\ProcessingInvoiceService;
use Illuminate\Http\Request;

class ProcessingInvoiceController extends Controller
{
    public function __construct(
        private ProcessingInvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request)
    {
        $q = ProcessingInvoice::query()
            ->with(['supplier:id,supplier_name', 'order:id,order_number'])
            ->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $q->where('supplier_id', (int) $request->supplier_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json($q->paginate((int) ($request->itemsPerPage ?? 15)));
    }

    public function show(int $id)
    {
        return response()->json(
            ProcessingInvoice::query()->with(['supplier', 'order', 'lines'])->findOrFail($id)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'processing_order_id' => 'nullable|exists:processing_orders,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'external_invoice_no' => 'nullable|string|max:128',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'tax_amount' => 'nullable|numeric|min:0',
            'capitalize_to_inventory' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.line_type' => 'nullable|string|max:32',
            'lines.*.description' => 'required|string|max:512',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.total' => 'nullable|numeric|min:0',
        ]);

        try {
            $invoice = $this->invoiceService->createDraft($data);

            return response()->json($invoice, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(int $id)
    {
        $invoice = ProcessingInvoice::query()->findOrFail($id);
        try {
            return response()->json($this->invoiceService->post($invoice));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
