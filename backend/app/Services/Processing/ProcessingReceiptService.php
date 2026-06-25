<?php

namespace App\Services\Processing;

use App\Enums\InventoryMovementType;
use App\Enums\ProcessingDocumentStatus;
use App\Enums\ProcessingOrderStatus;
use App\Models\Category;
use App\Models\ProcessingMaterialBalance;
use App\Models\ProcessingOrder;
use App\Models\ProcessingOrderLine;
use App\Models\ProcessingReceipt;
use App\Models\ProcessingReceiptLine;
use App\Models\TransactionType;
use App\Services\CategoryInventoryCostService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

class ProcessingReceiptService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryMovementLedgerService $ledger,
        private ProcessingAccountingService $accounting,
        private ProcessingCategoryResolverService $categoryResolver,
        private ProcessingOrderService $orderService,
        private ProcessingActivityLogger $activityLogger,
    ) {
    }

    public function createDraft(ProcessingOrder $order, array $data): ProcessingReceipt
    {
        if (! in_array($order->status, [
            ProcessingOrderStatus::InProgress->value,
            ProcessingOrderStatus::PartiallyReceived->value,
            ProcessingOrderStatus::Approved->value,
        ], true)) {
            throw new \InvalidArgumentException('لا يمكن استلام مواد لأمر غير نشط.');
        }

        $type = TransactionType::query()->where('code', 'PROCESSING_RECEIPT')->firstOrFail();
        $destStockId = (int) ($data['destination_stock_id'] ?? $order->destination_stock_id ?? $order->source_stock_id);

        return DB::transaction(function () use ($order, $data, $type, $destStockId) {
            $receipt = ProcessingReceipt::query()->create([
                'receipt_number' => $this->numbers->generate((int) $type->id),
                'processing_order_id' => $order->id,
                'processing_dispatch_note_id' => $data['processing_dispatch_note_id'] ?? null,
                'supplier_id' => $order->supplier_id,
                'destination_stock_id' => $destStockId,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'status' => ProcessingDocumentStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
            ]);

            $destStock = \App\Models\Stock::query()->findOrFail($destStockId);

            foreach ($data['lines'] as $row) {
                $orderLine = ProcessingOrderLine::query()
                    ->where('processing_order_id', $order->id)
                    ->findOrFail((int) $row['processing_order_line_id']);

                $good = (float) ($row['good_qty'] ?? 0);
                $damaged = (float) ($row['damaged_qty'] ?? 0);
                $rejected = (float) ($row['rejected_qty'] ?? 0);
                $total = $good + $damaged + $rejected;

                if ($total <= 0) {
                    throw new \InvalidArgumentException('يجب إدخال كمية مستلمة للسطر #' . $orderLine->id);
                }

                if ($total > $orderLine->qtyAtVendor() + 0.000001) {
                    throw new \InvalidArgumentException('الكمية المستلمة أكبر من المتاح لدى المعالج للسطر #' . $orderLine->id);
                }

                $sourceCat = Category::query()->findOrFail($orderLine->category_id);
                $destCat = $orderLine->destination_category_id
                    ? Category::query()->findOrFail($orderLine->destination_category_id)
                    : $this->categoryResolver->resolveInStock($sourceCat, $destStock);

                if (! $orderLine->destination_category_id) {
                    $orderLine->update(['destination_category_id' => $destCat->id]);
                }

                $returnCat = $sourceCat;

                ProcessingReceiptLine::query()->create([
                    'processing_receipt_id' => $receipt->id,
                    'processing_order_line_id' => $orderLine->id,
                    'category_id' => $orderLine->category_id,
                    'at_vendor_category_id' => $orderLine->at_vendor_category_id,
                    'destination_category_id' => $destCat->id,
                    'good_qty' => $good,
                    'damaged_qty' => $damaged,
                    'rejected_qty' => $rejected,
                    'material_unit_cost' => (float) ($orderLine->unit_material_cost ?? 0),
                    'allocated_service_cost' => (float) ($row['allocated_service_cost'] ?? 0),
                    'rejection_return_category_id' => $returnCat->id,
                ]);
            }

            return $receipt->fresh(['lines']);
        });
    }

    public function post(ProcessingReceipt $receipt): ProcessingReceipt
    {
        if ($receipt->status !== ProcessingDocumentStatus::Draft->value) {
            throw new \InvalidArgumentException('إذن الاستلام ليس في حالة مسودة.');
        }

        $receipt->load(['lines.orderLine', 'order', 'destinationStock']);

        return DB::transaction(function () use ($receipt) {
            $refType = 'processing_receipt';
            $journalLegs = [];
            $atVendorAccId = $this->accounting->resolveMaterialsAtVendorAccount()->id;
            $scrapAccId = $this->accounting->resolveScrapExpenseAccount()->id;
            $destAccId = $this->accounting->resolveStockAccount((int) $receipt->destination_stock_id)->id;
            $sourceAccId = $this->accounting->resolveStockAccount((int) $receipt->order->source_stock_id)->id;

            foreach ($receipt->lines as $line) {
                $atVendor = Category::query()->lockForUpdate()->findOrFail($line->at_vendor_category_id);
                $unitCost = (float) $line->material_unit_cost;
                $servicePerUnit = $line->good_qty > 0
                    ? (float) $line->allocated_service_cost / (float) $line->good_qty
                    : 0;

                $this->processQtyLeg(
                    $atVendor,
                    $line->destination_category_id ? Category::query()->lockForUpdate()->findOrFail($line->destination_category_id) : null,
                    (float) $line->good_qty,
                    $unitCost,
                    $servicePerUnit,
                    InventoryMovementType::SubcontractReceiptGoodOut,
                    InventoryMovementType::SubcontractReceiptGoodIn,
                    $refType,
                    (int) $receipt->id,
                    $receipt->receipt_number
                );

                if ((float) $line->good_qty > 0) {
                    $goodMaterial = round((float) $line->good_qty * $unitCost, 4);
                    $service = round((float) $line->allocated_service_cost, 4);
                    $journalLegs[] = [
                        'debit_account_id' => $destAccId,
                        'credit_account_id' => $atVendorAccId,
                        'amount' => $goodMaterial,
                        'description' => 'استلام جيد — مواد',
                    ];
                    if ($service > 0) {
                        $journalLegs[] = [
                            'debit_account_id' => $destAccId,
                            'credit_account_id' => $atVendorAccId,
                            'amount' => $service,
                            'description' => 'استلام جيد — تكلفة تشغيل',
                        ];
                    }
                }

                if ((float) $line->rejected_qty > 0) {
                    $returnCat = Category::query()->lockForUpdate()->findOrFail($line->rejection_return_category_id);
                    $this->processQtyLeg(
                        $atVendor,
                        $returnCat,
                        (float) $line->rejected_qty,
                        $unitCost,
                        0,
                        InventoryMovementType::SubcontractReceiptRejectedOut,
                        InventoryMovementType::SubcontractReceiptRejectedIn,
                        $refType,
                        (int) $receipt->id,
                        $receipt->receipt_number . ' (مرفوض)'
                    );
                    $amt = round((float) $line->rejected_qty * $unitCost, 4);
                    $journalLegs[] = [
                        'debit_account_id' => $sourceAccId,
                        'credit_account_id' => $atVendorAccId,
                        'amount' => $amt,
                        'description' => 'مرتجع مرفوض للمخزن',
                    ];
                }

                if ((float) $line->damaged_qty > 0) {
                    $damagedQty = (float) $line->damaged_qty;
                    $tc = round($damagedQty * $unitCost, 4);
                    $this->ledger->recordOutbound(
                        $atVendor,
                        InventoryMovementType::SubcontractReceiptDamagedOut,
                        $damagedQty,
                        $unitCost,
                        $tc,
                        true,
                        $refType,
                        (int) $receipt->id,
                        'تالف — ' . $receipt->receipt_number,
                        null,
                        auth()->user()?->name
                    );
                    $journalLegs[] = [
                        'debit_account_id' => $scrapAccId,
                        'credit_account_id' => $atVendorAccId,
                        'amount' => $tc,
                        'description' => 'كمية تالفة من التشغيل الخارجي',
                    ];
                }

                $orderLine = $line->orderLine;
                $orderLine->received_good_qty = (float) $orderLine->received_good_qty + (float) $line->good_qty;
                $orderLine->received_damaged_qty = (float) $orderLine->received_damaged_qty + (float) $line->damaged_qty;
                $orderLine->received_rejected_qty = (float) $orderLine->received_rejected_qty + (float) $line->rejected_qty;
                $orderLine->save();

                $bal = ProcessingMaterialBalance::query()->where([
                    'processing_order_id' => $receipt->processing_order_id,
                    'at_vendor_category_id' => $line->at_vendor_category_id,
                ])->first();
                if ($bal) {
                    $bal->qty_at_vendor = max(0, (float) $bal->qty_at_vendor - ((float) $line->good_qty + (float) $line->damaged_qty + (float) $line->rejected_qty));
                    $bal->save();
                }
            }

            $batchCode = 'SUB-RCV-' . $receipt->id;
            $dailyEntryId = $this->accounting->postReceiptJournal(
                $journalLegs,
                'إذن استلام تشغيل خارجي — ' . $receipt->receipt_number,
                $batchCode
            );

            $receipt->update([
                'status' => ProcessingDocumentStatus::Posted->value,
                'posted_at' => now(),
                'posted_by' => auth()->id(),
                'daily_entry_id' => $dailyEntryId,
            ]);

            $this->orderService->refreshOrderTotals($receipt->order);

            $this->activityLogger->log('processing_receipt', (int) $receipt->id, 'posted');

            return $receipt->fresh(['lines', 'order']);
        });
    }

    private function processQtyLeg(
        Category $from,
        ?Category $to,
        float $qty,
        float $unitCost,
        float $servicePerUnit,
        InventoryMovementType $outType,
        InventoryMovementType $inType,
        string $refType,
        int $refId,
        string $label
    ): void {
        if ($qty <= 0 || ! $to) {
            return;
        }

        $materialTc = round($qty * $unitCost, 4);
        $serviceTc = round($qty * $servicePerUnit, 4);
        $inboundTc = $materialTc + $serviceTc;
        $inboundUnit = $qty > 0 ? $inboundTc / $qty : 0;

        $this->ledger->recordOutbound(
            $from,
            $outType,
            $qty,
            $unitCost,
            $materialTc,
            true,
            $refType,
            $refId,
            $label,
            null,
            auth()->user()?->name
        );

        $this->ledger->recordInbound(
            $to,
            $inType,
            $qty,
            $inboundUnit,
            $inboundTc,
            true,
            $refType,
            $refId,
            $label,
            null,
            auth()->user()?->name
        );
    }
}
