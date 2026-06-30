<?php

namespace App\Services\Processing;

use App\Enums\InventoryMovementType;
use App\Enums\ProcessingDocumentStatus;
use App\Enums\ProcessingOrderStatus;
use App\Models\Category;
use App\Models\ProcessingDispatchLine;
use App\Models\ProcessingDispatchNote;
use App\Models\ProcessingMaterialBalance;
use App\Models\ProcessingOrder;
use App\Models\ProcessingOrderLine;
use App\Models\TransactionType;
use App\Services\CategoryInventoryCostService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

class ProcessingDispatchService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryMovementLedgerService $ledger,
        private ProcessingAccountingService $accounting,
        private ProcessingOrderService $orderService,
        private ProcessingCategoryResolverService $categoryResolver,
        private ProcessingActivityLogger $activityLogger,
        private ProcessingInvoiceService $invoiceService,
    ) {
    }

    public function createDraft(ProcessingOrder $order, array $data): ProcessingDispatchNote
    {
        if (! in_array($order->status, [
            ProcessingOrderStatus::Approved->value,
            ProcessingOrderStatus::InProgress->value,
            ProcessingOrderStatus::PartiallyReceived->value,
        ], true)) {
            throw new \InvalidArgumentException('يجب اعتماد أمر التشغيل قبل إنشاء إذن الصرف.');
        }

        $this->orderService->repairLineCategories($order);

        $type = TransactionType::query()->where('code', 'PROCESSING_DISPATCH')->firstOrFail();

        return DB::transaction(function () use ($order, $data, $type) {
            $order->loadMissing('sourceStock');

            $note = ProcessingDispatchNote::query()->create([
                'dispatch_number' => $this->numbers->generate((int) $type->id),
                'processing_order_id' => $order->id,
                'supplier_id' => $order->supplier_id,
                'source_stock_id' => $order->source_stock_id,
                'dispatch_date' => $data['dispatch_date'] ?? now()->toDateString(),
                'dispatch_type' => $data['dispatch_type'] ?? null,
                'status' => ProcessingDocumentStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
                'representative_type' => $data['representative_type'] ?? null,
                'shipping_company_id' => $data['shipping_company_id'] ?? null,
                'external_representative_name' => $data['external_representative_name'] ?? null,
            ]);

            foreach ($data['lines'] as $row) {
                $orderLine = ProcessingOrderLine::query()
                    ->where('processing_order_id', $order->id)
                    ->findOrFail((int) $row['processing_order_line_id']);

                $qty = (float) $row['quantity'];
                $remaining = (float) $orderLine->ordered_qty - (float) $orderLine->dispatched_qty;
                if ($qty <= 0 || $qty > $remaining + 0.000001) {
                    throw new \InvalidArgumentException('الكمية المطلوب صرفها غير صالحة للسطر #' . $orderLine->id);
                }

                $source = $this->resolveDispatchSourceCategory($order, $orderLine);
                $this->orderService->assertSufficientStock($order, $source, $qty);

                $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $source->id);

                ProcessingDispatchLine::query()->create([
                    'processing_dispatch_note_id' => $note->id,
                    'processing_order_line_id' => $orderLine->id,
                    'category_id' => $source->id,
                    'at_vendor_category_id' => $orderLine->at_vendor_category_id,
                    'quantity' => $qty,
                    'unit_cost' => $avg,
                    'total_cost' => round($qty * $avg, 4),
                ]);
            }

            return $note->fresh(['lines.category', 'lines.atVendorCategory', 'supplier']);
        });
    }

    public function post(ProcessingDispatchNote $note): ProcessingDispatchNote
    {
        if ($note->status !== ProcessingDocumentStatus::Draft->value) {
            throw new \InvalidArgumentException('إذن الصرف ليس في حالة مسودة.');
        }

        $note->load(['lines.orderLine', 'order.sourceStock']);

        return DB::transaction(function () use ($note) {
            $totalCost = 0.0;
            $refType = 'processing_dispatch_note';
            $order = $note->order;

            foreach ($note->lines as $line) {
                $orderLine = $line->orderLine;
                $source = $this->resolveDispatchSourceCategory($order, $orderLine);
                if ((int) $line->category_id !== (int) $source->id) {
                    $line->category_id = $source->id;
                    $line->save();
                }
                if ((int) $orderLine->category_id !== (int) $source->id) {
                    $orderLine->category_id = $source->id;
                    $orderLine->save();
                }

                $this->orderService->assertSufficientStock($order, $source, (float) $line->quantity);

                $source = Category::query()->lockForUpdate()->findOrFail($source->id);
                $atVendor = Category::query()->lockForUpdate()->findOrFail($line->at_vendor_category_id);
                $qty = (float) $line->quantity;
                $unitCost = (float) $line->unit_cost;
                $tc = (float) $line->total_cost;

                $this->ledger->recordOutbound(
                    $source,
                    InventoryMovementType::SubcontractDispatchOut,
                    $qty,
                    $unitCost,
                    $tc,
                    true,
                    $refType,
                    (int) $note->id,
                    'إذن صرف تشغيل خارجي ' . $note->dispatch_number,
                    null,
                    auth()->user()?->name
                );

                $this->ledger->recordInbound(
                    $atVendor,
                    InventoryMovementType::SubcontractDispatchIn,
                    $qty,
                    $unitCost,
                    $tc,
                    true,
                    $refType,
                    (int) $note->id,
                    'استلام لدى مندوب — ' . $note->dispatch_number,
                    null,
                    auth()->user()?->name
                );

                $orderLine->dispatched_qty = (float) $orderLine->dispatched_qty + $qty;
                $orderLine->unit_material_cost = $unitCost;
                $orderLine->save();

                $bal = ProcessingMaterialBalance::query()->firstOrCreate(
                    [
                        'processing_order_id' => $note->processing_order_id,
                        'at_vendor_category_id' => $atVendor->id,
                    ],
                    [
                        'supplier_id' => $note->supplier_id,
                        'category_id' => $source->id,
                        'qty_at_vendor' => 0,
                    ]
                );
                $bal->qty_at_vendor = (float) $bal->qty_at_vendor + $qty;
                $bal->save();

                $totalCost += $tc;
            }

            $batchCode = 'SUB-DSP-' . $note->id;
            $dailyEntryId = $this->accounting->postDispatchReclassification(
                $totalCost,
                (int) $note->source_stock_id,
                $note->dispatch_number,
                $batchCode
            );

            $note->update([
                'status' => ProcessingDocumentStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'daily_entry_id' => $dailyEntryId,
            ]);

            $this->orderService->refreshOrderTotals($note->order);

            $this->activityLogger->log('processing_dispatch_note', (int) $note->id, 'posted', null, [
                'total_cost' => $totalCost,
            ]);

            return $note->fresh(['lines', 'order']);
        });
    }

    private function resolveDispatchSourceCategory(ProcessingOrder $order, ProcessingOrderLine $orderLine): Category
    {
        $picked = Category::query()->findOrFail($orderLine->category_id);

        return $this->orderService->resolveSourceCategory($order, $picked);
    }

    /**
     * إنشاء أمر معتمد + ترحيل إذن الصرف + ترحيل ذمة المورد (AP) في خطوة واحدة.
     */
    public function submitVoucher(array $data): array
    {
        $this->validateRepresentative($data);

        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            $serviceTotal = (float) ($data['expected_service_total'] ?? 0);
            if ($serviceTotal <= 0) {
                $serviceTotal = array_sum(array_map(
                    fn ($row) => (float) ($row['expected_service_amount'] ?? 0),
                    $lines
                ));
            }

            $order = $this->orderService->create(array_merge($data, [
                'auto_approve' => true,
                'expected_service_total' => $serviceTotal,
            ]));

            $order->load('lines');

            $dispatchLines = $order->lines->map(fn (ProcessingOrderLine $line) => [
                'processing_order_line_id' => $line->id,
                'quantity' => (float) $line->ordered_qty,
            ])->all();

            $note = $this->createDraft($order, array_merge($data, ['lines' => $dispatchLines]));
            $note = $this->post($note);

            $invoice = null;
            if ($serviceTotal > 0.000001) {
                $invoiceLines = [];
                foreach ($order->lines as $line) {
                    $amount = (float) $line->expected_service_amount;
                    if ($amount <= 0) {
                        continue;
                    }
                    $invoiceLines[] = [
                        'line_type' => 'other',
                        'description' => 'تكلفة تشغيل — ' . ($line->category?->category_name ?? 'صنف'),
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ];
                }

                if (empty($invoiceLines)) {
                    $invoiceLines[] = [
                        'line_type' => 'other',
                        'description' => 'تكلفة تشغيل خارجي — ' . $note->dispatch_number,
                        'quantity' => 1,
                        'unit_price' => $serviceTotal,
                        'total' => $serviceTotal,
                    ];
                }

                $invoice = $this->invoiceService->createDraft([
                    'processing_order_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'invoice_date' => $data['dispatch_date'] ?? now()->toDateString(),
                    'external_invoice_no' => $data['external_invoice_no'] ?? null,
                    'capitalize_to_inventory' => false,
                    'notes' => 'مُنشأة تلقائياً من إذن الصرف ' . $note->dispatch_number,
                    'lines' => $invoiceLines,
                ]);
                $invoice = $this->invoiceService->post($invoice);
            }

            $order->refresh()->load([
                'supplier',
                'lines.category',
                'dispatchNotes.lines',
                'invoices',
            ]);

            return [
                'order' => $order,
                'dispatch' => $note->fresh(['lines', 'shippingCompany:id,name,type']),
                'invoice' => $invoice,
            ];
        });
    }

    private function validateRepresentative(array $data): void
    {
        $type = $data['representative_type'] ?? null;
        if ($type === 'internal' && empty($data['shipping_company_id'])) {
            throw new \InvalidArgumentException('لا يوجد مندوب في النظام — أضف مندوباً من إدارة الشحن أو اختر مندوباً خارجياً.');
        }
        if ($type === 'external' && empty(trim((string) ($data['external_representative_name'] ?? '')))) {
            throw new \InvalidArgumentException('أدخل اسم المندوب الخارجي.');
        }
    }
}
