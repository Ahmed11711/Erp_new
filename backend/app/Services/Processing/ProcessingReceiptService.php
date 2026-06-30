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
                $destCat = $this->resolveReceiptDestination($row, $orderLine, $sourceCat, $destStock);

                if ((int) $orderLine->destination_category_id !== (int) $destCat->id) {
                    $orderLine->update(['destination_category_id' => $destCat->id]);
                }

                $returnCat = $sourceCat;

                $materialUnitCost = (float) ($orderLine->unit_material_cost ?? 0);
                $allocatedService = $this->resolveAllocatedService($row, $orderLine, $good);
                [$destUnitCost, $destSellPrice] = $this->resolveDestPricing(
                    $row,
                    $destCat,
                    $materialUnitCost,
                    $allocatedService,
                    $good
                );

                ProcessingReceiptLine::query()->create([
                    'processing_receipt_id' => $receipt->id,
                    'processing_order_line_id' => $orderLine->id,
                    'category_id' => $orderLine->category_id,
                    'at_vendor_category_id' => $orderLine->at_vendor_category_id,
                    'destination_category_id' => $destCat->id,
                    'good_qty' => $good,
                    'damaged_qty' => $damaged,
                    'rejected_qty' => $rejected,
                    'material_unit_cost' => $materialUnitCost,
                    'allocated_service_cost' => $allocatedService,
                    'dest_unit_cost' => $destUnitCost,
                    'dest_sell_price' => $destSellPrice,
                    'rejection_return_category_id' => $returnCat->id,
                ]);
            }

            return $receipt->fresh(['lines']);
        });
    }

    /**
     * Decide which product receives the good (processed) quantity:
     *  1. A new product the user named at receipt time (created as a raw material).
     *  2. An existing product the user explicitly picked.
     *  3. The destination previously stored on the order line.
     *  4. Fallback: a shadow of the source category in the destination warehouse.
     */
    private function resolveReceiptDestination(
        array $row,
        ProcessingOrderLine $orderLine,
        Category $sourceCat,
        \App\Models\Stock $destStock
    ): Category {
        $newName = trim((string) ($row['new_product_name'] ?? ''));
        if ($newName !== '') {
            return $this->categoryResolver->createNamedProduct($sourceCat, $destStock, $newName, 'raw_material');
        }

        if (! empty($row['destination_category_id'])) {
            return Category::query()->findOrFail((int) $row['destination_category_id']);
        }

        if ($orderLine->destination_category_id) {
            return Category::query()->findOrFail($orderLine->destination_category_id);
        }

        return $this->categoryResolver->resolveInStock($sourceCat, $destStock);
    }

    /**
     * Processing (service) cost only for the good quantity. This is the "تكلفة الاستلام"
     * the user sees — it represents the processing fee, NOT the material or item price.
     * The value entered (received_total_cost) is taken as the processing cost directly.
     */
    private function resolveAllocatedService(
        array $row,
        ProcessingOrderLine $orderLine,
        float $goodQty
    ): float {
        if ($goodQty <= 0) {
            return 0.0;
        }

        if (isset($row['received_total_cost']) && $row['received_total_cost'] !== null && $row['received_total_cost'] !== '') {
            return round(max(0.0, (float) $row['received_total_cost']), 4);
        }

        if (isset($row['allocated_service_cost']) && $row['allocated_service_cost'] !== null) {
            return round(max(0.0, (float) $row['allocated_service_cost']), 4);
        }

        $orderedQty = (float) $orderLine->ordered_qty;
        $servicePerUnit = $orderedQty > 0 ? (float) $orderLine->expected_service_amount / $orderedQty : 0.0;

        return round(max(0.0, $servicePerUnit) * $goodQty, 4);
    }

    /**
     * The destination item's unit cost & selling price are set manually by the user
     * (no weighted average). If not supplied we keep the item's current values, and
     * for a brand-new product fall back to (material + processing) per unit as a hint.
     *
     * @return array{0: float, 1: ?float}  [unitCost, sellPrice|null]
     */
    private function resolveDestPricing(
        array $row,
        Category $destCat,
        float $materialUnitCost,
        float $allocatedService,
        float $goodQty
    ): array {
        $hasUnit = isset($row['dest_unit_cost']) && $row['dest_unit_cost'] !== null && $row['dest_unit_cost'] !== '';
        $hasSell = isset($row['dest_sell_price']) && $row['dest_sell_price'] !== null && $row['dest_sell_price'] !== '';

        if ($hasUnit) {
            $unitCost = round(max(0.0, (float) $row['dest_unit_cost']), 4);
        } else {
            $current = (float) ($destCat->unit_price ?? 0);
            if ($current > 0.00001) {
                $unitCost = round($current, 4);
            } else {
                $servicePerUnit = $goodQty > 0 ? $allocatedService / $goodQty : 0.0;
                $unitCost = round($materialUnitCost + $servicePerUnit, 4);
            }
        }

        $sellPrice = $hasSell ? round(max(0.0, (float) $row['dest_sell_price']), 4) : null;

        return [$unitCost, $sellPrice];
    }

    public function post(ProcessingReceipt $receipt): ProcessingReceipt
    {
        if ($receipt->status !== ProcessingDocumentStatus::Draft->value) {
            throw new \InvalidArgumentException('إذن الاستلام ليس في حالة مسودة.');
        }

        $receipt->load(['lines.orderLine', 'lines.destinationCategory', 'order', 'destinationStock']);

        return DB::transaction(function () use ($receipt) {
            $refType = 'processing_receipt';
            $journalLegs = [];
            $atVendorAccId = $this->accounting->resolveMaterialsAtVendorAccount()->id;
            $scrapAccId = $this->accounting->resolveScrapExpenseAccount()->id;
            $serviceAccId = $this->accounting->resolveServiceExpenseAccount()->id;
            $varianceAccId = $scrapAccId;
            $sourceAccId = $this->accounting->resolveStockAccount((int) $receipt->order->source_stock_id)->id;

            $destAccCache = [];
            $resolveDestAcc = function (int $stockId) use (&$destAccCache) {
                return $destAccCache[$stockId] ??= $this->accounting->resolveStockAccount($stockId)->id;
            };

            foreach ($receipt->lines as $line) {
                $destStockId = (int) (($line->destinationCategory->stock_id ?? null) ?: $receipt->destination_stock_id);
                $destAccId = $resolveDestAcc($destStockId);

                $atVendor = Category::query()->lockForUpdate()->findOrFail($line->at_vendor_category_id);
                $unitCost = (float) $line->material_unit_cost;

                if ((float) $line->good_qty > 0) {
                    $goodQty = (float) $line->good_qty;
                    $materialValue = round($goodQty * $unitCost, 4);
                    $serviceValue = round((float) $line->allocated_service_cost, 4);

                    $destCat = $line->destination_category_id
                        ? Category::query()->lockForUpdate()->findOrFail($line->destination_category_id)
                        : null;

                    $manualUnitCost = (float) ($line->dest_unit_cost ?? 0);
                    if ($manualUnitCost <= 0.00001) {
                        $manualUnitCost = $goodQty > 0 ? round(($materialValue + $serviceValue) / $goodQty, 4) : 0.0;
                    }

                    $invDelta = $this->receiveGoodQty(
                        $atVendor,
                        $destCat,
                        $goodQty,
                        $unitCost,
                        $materialValue,
                        $manualUnitCost,
                        $line->dest_sell_price !== null ? (float) $line->dest_sell_price : null,
                        $refType,
                        (int) $receipt->id,
                        $receipt->receipt_number
                    );

                    // Fund the destination inventory increase from materials-at-vendor,
                    // capitalize the processing fee, and route any difference (because the
                    // user set the item cost manually) to the processing variance account
                    // so the journal stays balanced and matches the inventory subledger.
                    if ($materialValue > 0.00001) {
                        $journalLegs[] = [
                            'debit_account_id' => $destAccId,
                            'credit_account_id' => $atVendorAccId,
                            'amount' => $materialValue,
                            'description' => 'استلام — تفريغ قيمة المواد لدى المندوب',
                        ];
                    }
                    if ($serviceValue > 0.00001) {
                        $journalLegs[] = [
                            'debit_account_id' => $destAccId,
                            'credit_account_id' => $serviceAccId,
                            'amount' => $serviceValue,
                            'description' => 'رسملة تكلفة المعالجة على الصنف المستلم',
                        ];
                    }
                    $variance = round($invDelta - $materialValue - $serviceValue, 4);
                    if ($variance > 0.00001) {
                        $journalLegs[] = [
                            'debit_account_id' => $destAccId,
                            'credit_account_id' => $varianceAccId,
                            'amount' => $variance,
                            'description' => 'فروق تقييم الصنف المستلم (تشغيل خارجي)',
                        ];
                    } elseif ($variance < -0.00001) {
                        $journalLegs[] = [
                            'debit_account_id' => $varianceAccId,
                            'credit_account_id' => $destAccId,
                            'amount' => -$variance,
                            'description' => 'فروق تقييم الصنف المستلم (تشغيل خارجي)',
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

    /**
     * Receive the good (processed) quantity onto the destination product:
     *  - material leaves "materials at vendor" (quantity + valuation down at material cost),
     *  - the destination item quantity is increased,
     *  - the destination item is revalued to the user's manual unit cost & selling price
     *    (overwrite — NO weighted average).
     *
     * @return float  Change in the destination item's inventory value (total_price delta).
     */
    private function receiveGoodQty(
        Category $atVendor,
        ?Category $dest,
        float $qty,
        float $materialUnitCost,
        float $materialValue,
        float $manualUnitCost,
        ?float $manualSellPrice,
        string $refType,
        int $refId,
        string $label
    ): float {
        if ($qty <= 0 || ! $dest) {
            return 0.0;
        }

        $this->ledger->recordOutbound(
            $atVendor,
            InventoryMovementType::SubcontractReceiptGoodOut,
            $qty,
            $materialUnitCost,
            $materialValue,
            true,
            $refType,
            $refId,
            $label,
            null,
            auth()->user()?->name
        );

        $priorTotal = (float) ($dest->total_price ?? 0);
        $receivedValue = round($qty * $manualUnitCost, 4);

        // Add quantity only; valuation is set explicitly below (no weighted average).
        $this->ledger->recordInbound(
            $dest,
            InventoryMovementType::SubcontractReceiptGoodIn,
            $qty,
            $manualUnitCost,
            $receivedValue,
            false,
            $refType,
            $refId,
            $label,
            null,
            auth()->user()?->name
        );

        $fresh = Category::query()->lockForUpdate()->findOrFail($dest->id);
        $newQty = (float) $fresh->quantity;
        $fresh->unit_price = round($manualUnitCost, 4);
        $fresh->total_price = round($manualUnitCost * $newQty, 4);
        if ($manualSellPrice !== null && $manualSellPrice > 0.00001) {
            $fresh->category_price = round($manualSellPrice, 4);
            $fresh->sell_total_price = round($manualSellPrice * $newQty, 4);
        }
        $fresh->save();

        return round((float) $fresh->total_price - $priorTotal, 4);
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
