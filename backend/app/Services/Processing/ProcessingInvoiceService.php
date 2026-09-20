<?php

namespace App\Services\Processing;

use App\Enums\ProcessingDocumentStatus;
use App\Models\ProcessingInvoice;
use App\Models\ProcessingInvoiceLine;
use App\Models\ProcessingOrder;
use App\Models\Supplier;
use App\Models\TransactionType;
use App\Services\Documents\DocumentNumberService;
use Illuminate\Support\Facades\DB;

class ProcessingInvoiceService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private ProcessingAccountingService $accounting,
        private ProcessingOrderService $orderService,
        private ProcessingActivityLogger $activityLogger,
    ) {
    }

    public function createDraft(array $data): ProcessingInvoice
    {
        $type = TransactionType::query()->where('code', 'PROCESSING_INVOICE')->firstOrFail();

        return DB::transaction(function () use ($data, $type) {
            $lines = $data['lines'] ?? [];
            $subtotal = 0.0;
            foreach ($lines as $row) {
                $subtotal += round((float) ($row['total'] ?? ((float) $row['quantity'] * (float) $row['unit_price'])), 4);
            }
            $tax = round((float) ($data['tax_amount'] ?? 0), 4);
            $grand = round($subtotal + $tax, 4);

            $invoice = ProcessingInvoice::query()->create([
                'invoice_number' => $this->numbers->generate((int) $type->id),
                'external_invoice_no' => $data['external_invoice_no'] ?? null,
                'processing_order_id' => $data['processing_order_id'] ?? null,
                'supplier_id' => (int) $data['supplier_id'],
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'grand_total' => $grand,
                'paid_amount' => 0,
                'due_amount' => $grand,
                'status' => ProcessingDocumentStatus::Draft->value,
                'capitalize_to_inventory' => (bool) ($data['capitalize_to_inventory'] ?? true),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $row) {
                $total = round((float) ($row['total'] ?? ((float) $row['quantity'] * (float) $row['unit_price'])), 4);
                ProcessingInvoiceLine::query()->create([
                    'processing_invoice_id' => $invoice->id,
                    'line_type' => $row['line_type'] ?? 'other',
                    'description' => $row['description'],
                    'quantity' => (float) ($row['quantity'] ?? 1),
                    'unit_price' => (float) $row['unit_price'],
                    'total' => $total,
                    'processing_receipt_line_id' => $row['processing_receipt_line_id'] ?? null,
                ]);
            }

            return $invoice->fresh(['lines', 'supplier', 'order']);
        });
    }

    public function post(ProcessingInvoice $invoice): ProcessingInvoice
    {
        if ($invoice->status !== ProcessingDocumentStatus::Draft->value) {
            throw new \InvalidArgumentException('الفاتورة ليست في حالة مسودة.');
        }

        $invoice->load(['supplier', 'order']);

        return DB::transaction(function () use ($invoice) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($invoice->supplier_id);
            $amount = (float) $invoice->grand_total;

            $destStockId = $invoice->order?->destination_stock_id ?? $invoice->order?->source_stock_id;

            $batchCode = 'SUB-INV-' . $invoice->id;
            $dailyEntryId = $this->accounting->postInvoice(
                $supplier,
                $amount,
                $invoice->invoice_number,
                $batchCode,
                (bool) $invoice->capitalize_to_inventory,
                $destStockId ? (int) $destStockId : null
            );

            $balanceBefore = (float) $supplier->balance;
            $supplier->last_balance = $balanceBefore;
            $supplier->balance = $balanceBefore + $amount;
            $supplier->save();

            DB::table('supplier_balance')->insert([
                'invoice_id' => null,
                'supplierpay_id' => null,
                'processing_invoice_id' => $invoice->id,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $supplier->balance,
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invoice->update([
                'status' => 'posted',
                'daily_entry_id' => $dailyEntryId,
            ]);

            if ($invoice->processing_order_id) {
                $order = ProcessingOrder::query()->find($invoice->processing_order_id);
                if ($order) {
                    $order->total_service_cost = (float) $order->total_service_cost + $amount;
                    $order->save();
                }
            }

            $this->activityLogger->log('processing_invoice', (int) $invoice->id, 'posted', null, [
                'grand_total' => $amount,
            ]);

            return $invoice->fresh(['lines', 'supplier', 'order']);
        });
    }

    public function recordPayment(ProcessingInvoice $invoice, float $amount): ProcessingInvoice
    {
        $invoice->refresh();
        $newPaid = (float) $invoice->paid_amount + $amount;
        $due = max(0, (float) $invoice->grand_total - $newPaid);

        $status = $due <= 0.00001 ? 'paid' : 'partially_paid';

        $invoice->update([
            'paid_amount' => $newPaid,
            'due_amount' => $due,
            'status' => $status,
        ]);

        return $invoice->fresh();
    }
}
