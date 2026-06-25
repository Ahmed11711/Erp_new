<?php

namespace App\Http\Controllers\Processing;

use App\Http\Controllers\Controller;
use App\Models\ProcessingOrder;
use App\Models\ProcessingReceipt;
use App\Services\Processing\ProcessingReceiptService;
use Illuminate\Http\Request;

class ProcessingReceiptController extends Controller
{
    public function __construct(
        private ProcessingReceiptService $receiptService,
    ) {
    }

    public function index(Request $request)
    {
        $q = ProcessingReceipt::query()
            ->with(['supplier:id,supplier_name', 'order:id,order_number'])
            ->orderByDesc('id');

        if ($request->filled('processing_order_id')) {
            $q->where('processing_order_id', (int) $request->processing_order_id);
        }

        return response()->json($q->paginate((int) ($request->itemsPerPage ?? 15)));
    }

    public function show(int $id)
    {
        $receipt = ProcessingReceipt::query()
            ->with(['supplier', 'order.lines', 'destinationStock', 'lines'])
            ->findOrFail($id);

        return response()->json($receipt);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'processing_order_id' => 'required|exists:processing_orders,id',
            'processing_dispatch_note_id' => 'nullable|exists:processing_dispatch_notes,id',
            'destination_stock_id' => 'nullable|exists:stocks,id',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.processing_order_line_id' => 'required|exists:processing_order_lines,id',
            'lines.*.good_qty' => 'nullable|numeric|min:0',
            'lines.*.received_qty' => 'nullable|numeric|min:0',
            'lines.*.damaged_qty' => 'nullable|numeric|min:0',
            'lines.*.rejected_qty' => 'nullable|numeric|min:0',
            'lines.*.allocated_service_cost' => 'nullable|numeric|min:0',
        ]);

        $order = ProcessingOrder::query()->findOrFail((int) $data['processing_order_id']);

        foreach ($data['lines'] as &$line) {
            if (! isset($line['good_qty']) && isset($line['received_qty'])) {
                $line['good_qty'] = $line['received_qty'];
            }
        }
        unset($line);

        try {
            $receipt = $this->receiptService->createDraft($order, $data);

            return response()->json($receipt, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(int $id)
    {
        $receipt = ProcessingReceipt::query()->with('lines')->findOrFail($id);
        try {
            return response()->json($this->receiptService->post($receipt));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
