<?php

namespace App\Services\Processing;

use App\Enums\InventoryMovementType;
use App\Enums\ProcessingDocumentStatus;
use App\Enums\ProcessingOrderStatus;
use App\Models\Category;
use App\Models\CategoryCostHistory;
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

                if ($good <= 0) {
                    throw new \InvalidArgumentException('أدخل الكمية المستلمة (الجيدة) للسطر #' . $orderLine->id);
                }

                if ($total > $orderLine->qtyAtVendor() + 0.000001) {
                    throw new \InvalidArgumentException('الكمية المستلمة + الهالك أكبر من المتاح لدى المعالج للسطر #' . $orderLine->id);
                }

                $sourceCat = Category::query()->find($orderLine->category_id);
                if (! $sourceCat) {
                    throw new \InvalidArgumentException(
                        'صنف الصرف الأصلي غير موجود في قاعدة البيانات (#' . (int) $orderLine->category_id . ').'
                    );
                }

                $destCat = $this->resolveReceiptDestination($row, $orderLine, $sourceCat, $destStock);

                if ((int) $orderLine->destination_category_id !== (int) $destCat->id) {
                    $orderLine->update(['destination_category_id' => $destCat->id]);
                }

                $returnCat = $sourceCat;

                $materialUnitCost = (float) ($orderLine->unit_material_cost ?? 0);
                $allocatedService = $this->resolveAllocatedService($row, $orderLine, $good);
                $remainingQty = max(0.0, $orderLine->qtyAtVendor() - $total);
                $suggestedUnitCost = $this->computeSuggestedUnitCost(
                    $materialUnitCost,
                    $good,
                    $damaged,
                    $allocatedService,
                    $remainingQty
                );
                $costMode = $this->resolveCostApplyMode($row);
                [$destUnitCost, $destSellPrice] = $this->resolveDestPricing(
                    $row,
                    $destCat,
                    $suggestedUnitCost,
                    $costMode
                );

                $priorQty = (float) ($destCat->quantity ?? 0);
                $priorUnit = CategoryInventoryCostService::averageCostForCategoryIssue((int) $destCat->id);

                ProcessingReceiptLine::query()->create([
                    'processing_receipt_id' => $receipt->id,
                    'processing_order_line_id' => $orderLine->id,
                    'category_id' => $orderLine->category_id,
                    'at_vendor_category_id' => null,
                    'destination_category_id' => $destCat->id,
                    'good_qty' => $good,
                    'damaged_qty' => $damaged,
                    'rejected_qty' => $rejected,
                    'material_unit_cost' => $materialUnitCost,
                    'allocated_service_cost' => $allocatedService,
                    'dest_unit_cost' => $destUnitCost,
                    'dest_sell_price' => $destSellPrice,
                    'cost_apply_mode' => $costMode,
                    'suggested_unit_cost' => $suggestedUnitCost,
                    'prior_unit_cost' => $priorUnit,
                    'prior_qty' => $priorQty,
                    'rejection_return_category_id' => $returnCat->id,
                ]);
            }

            return $receipt->fresh(['lines']);
        });
    }

    /**
     * تكلفة وحدة الصنف المجهز = (تكلفة مادة المستلم + مادة الهالك + الخدمة المخصّصة) ÷ الكمية الجيّدة.
     *
     * الهالك يُرسمَل داخل تكلفة الصنف المستلم (خسارة طبيعية)، والخدمة موزّعة نسبياً على
     * الكمية الجيّدة أصلاً، لذا المقام هو الكمية الجيّدة فقط. هذا يضمن اتزان القيود:
     *   فرق التقييم = قيمة مادة الهالك، وصافي حساب الهالك = صفر، وتفريغ «مواد لدى المندوب» كاملاً.
     * المعامل $remainingQty محفوظ للتوافق فقط ولا يدخل في الحساب.
     */
    public function computeSuggestedUnitCost(
        float $materialUnitCost,
        float $goodQty,
        float $wasteQty,
        float $allocatedService,
        float $remainingQty = 0.0
    ): float {
        if ($goodQty <= 0.0000001) {
            return 0.0;
        }

        $consumedQty = max(0.0, $goodQty) + max(0.0, $wasteQty);
        $total = ($consumedQty * max(0.0, $materialUnitCost)) + max(0.0, $allocatedService);

        return round($total / $goodQty, 4);
    }

    private function resolveCostApplyMode(array $row): string
    {
        $mode = (string) ($row['cost_apply_mode'] ?? ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE);
        if (! in_array($mode, [
            ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE,
            ProcessingReceiptLine::COST_MODE_OVERWRITE,
        ], true)) {
            return ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE;
        }

        return $mode;
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
            $picked = Category::query()->find((int) $row['destination_category_id']);
            if (! $picked) {
                throw new \InvalidArgumentException(
                    'صنف الاستلام المختار غير موجود (#' . (int) $row['destination_category_id'] . '). اختر صنفاً آخر من القائمة.'
                );
            }

            return $picked;
        }

        if ($orderLine->destination_category_id) {
            $stored = Category::query()->find($orderLine->destination_category_id);
            if ($stored) {
                return $stored;
            }
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
     * @return array{0: float, 1: ?float}  [unitCost, sellPrice|null]
     */
    private function resolveDestPricing(
        array $row,
        Category $destCat,
        float $suggestedUnitCost,
        string $costMode
    ): array {
        $hasUnit = isset($row['dest_unit_cost']) && $row['dest_unit_cost'] !== null && $row['dest_unit_cost'] !== '';
        $hasSell = isset($row['dest_sell_price']) && $row['dest_sell_price'] !== null && $row['dest_sell_price'] !== '';

        if ($hasUnit) {
            $unitCost = round(max(0.0, (float) $row['dest_unit_cost']), 4);
        } else {
            $unitCost = $suggestedUnitCost;
            if ($costMode === ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE) {
                $priorQty = (float) ($destCat->quantity ?? 0);
                $priorUnit = CategoryInventoryCostService::averageCostForCategoryIssue((int) $destCat->id);
                // عند عدم إدخال تكلفة يدوياً تُحفظ تكلفة طبقة الاستلام المقترحة؛
                // المتوسط المرجّح يُحسب عند الترحيل على رصيد الصنف.
                if ($priorQty > 0.0000001 && $priorUnit > 0 && $suggestedUnitCost <= 0) {
                    $unitCost = round($priorUnit, 4);
                }
            }
            if ($unitCost <= 0.00001) {
                $current = CategoryInventoryCostService::averageCostForCategoryIssue((int) $destCat->id);
                $unitCost = $current > 0 ? round($current, 4) : 0.0;
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

                $unitCost = (float) $line->material_unit_cost;
                $costMode = (string) ($line->cost_apply_mode ?: ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE);

                if ((float) $line->good_qty > 0) {
                    $goodQty = (float) $line->good_qty;
                    $wasteQty = (float) $line->damaged_qty;
                    $materialGoodValue = round($goodQty * $unitCost, 4);
                    $materialWasteValue = round($wasteQty * $unitCost, 4);
                    $serviceValue = round((float) $line->allocated_service_cost, 4);

                    $destCat = $line->destination_category_id
                        ? Category::query()->lockForUpdate()->findOrFail($line->destination_category_id)
                        : null;

                    $priorQty = $destCat ? (float) ($destCat->quantity ?? 0) : 0.0;
                    $priorUnit = $destCat
                        ? CategoryInventoryCostService::averageCostForCategoryIssue((int) $destCat->id)
                        : 0.0;

                    $orderLineForQty = $line->orderLine;
                    $availableBefore = $orderLineForQty ? (float) $orderLineForQty->qtyAtVendor() : ($goodQty + $wasteQty);
                    $remainingQty = max(0.0, $availableBefore - $goodQty - $wasteQty - (float) $line->rejected_qty);

                    $receiptUnitCost = (float) ($line->dest_unit_cost ?? 0);
                    if ($receiptUnitCost <= 0.00001) {
                        $receiptUnitCost = $this->computeSuggestedUnitCost(
                            $unitCost,
                            $goodQty,
                            $wasteQty,
                            $serviceValue,
                            $remainingQty
                        );
                    }

                    $receiptLayerTotal = round($goodQty * $receiptUnitCost, 4);

                    // المواد خرجت مسبقاً عند الصرف؛ هنا دخول للصنف المجهز فقط + قيد محاسبي من حساب لدى المندوب.
                    $invDelta = $this->receiveGoodQty(
                        $destCat,
                        $goodQty,
                        $receiptUnitCost,
                        $receiptLayerTotal,
                        $costMode,
                        $line->dest_sell_price !== null ? (float) $line->dest_sell_price : null,
                        $refType,
                        (int) $receipt->id,
                        $receipt->receipt_number
                    );

                    if ($destCat) {
                        $freshDest = Category::query()->lockForUpdate()->findOrFail($destCat->id);
                        $line->prior_qty = $priorQty;
                        $line->prior_unit_cost = $priorUnit;
                        $line->suggested_unit_cost = $this->computeSuggestedUnitCost(
                            $unitCost,
                            $goodQty,
                            $wasteQty,
                            $serviceValue,
                            $remainingQty
                        );
                        $line->resulting_unit_cost = CategoryInventoryCostService::averageCostForCategoryIssue((int) $freshDest->id);
                        $line->dest_unit_cost = $receiptUnitCost;
                        $line->cost_apply_mode = $costMode;
                        $line->save();

                        $this->writeCostHistory(
                            $freshDest,
                            $receipt,
                            $line,
                            $costMode,
                            $goodQty,
                            $receiptUnitCost,
                            $receiptLayerTotal,
                            $wasteQty,
                            $materialWasteValue
                        );
                    }

                    // تفريغ قيمة المواد الجيدة + رسملة المعالجة + فروق التقييم (تشمل تحميل الهالك على تكلفة الصنف).
                    if ($materialGoodValue > 0.00001) {
                        $journalLegs[] = [
                            'debit_account_id' => $destAccId,
                            'credit_account_id' => $atVendorAccId,
                            'amount' => $materialGoodValue,
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
                    $variance = round($invDelta - $materialGoodValue - $serviceValue, 4);
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
                    $rejQty = (float) $line->rejected_qty;
                    $amt = round($rejQty * $unitCost, 4);
                    // إرجاع للمخزن المصدر (كانت المواد خارجة عند الصرف).
                    $this->ledger->recordInbound(
                        $returnCat,
                        InventoryMovementType::SubcontractReceiptRejectedIn,
                        $rejQty,
                        $unitCost,
                        $amt,
                        true,
                        $refType,
                        (int) $receipt->id,
                        $receipt->receipt_number . ' (مرفوض)',
                        null,
                        auth()->user()?->name
                    );
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
                    // لا حركة مخزون للهالك — المادة خرجت عند الصرف؛ قيد مصروف فقط.
                    $journalLegs[] = [
                        'debit_account_id' => $scrapAccId,
                        'credit_account_id' => $atVendorAccId,
                        'amount' => $tc,
                        'description' => 'كمية هالكة من التشغيل الخارجي',
                    ];
                }

                $orderLine = $line->orderLine;
                $orderLine->received_good_qty = (float) $orderLine->received_good_qty + (float) $line->good_qty;
                $orderLine->received_damaged_qty = (float) $orderLine->received_damaged_qty + (float) $line->damaged_qty;
                $orderLine->received_rejected_qty = (float) $orderLine->received_rejected_qty + (float) $line->rejected_qty;
                $orderLine->save();

                $bal = ProcessingMaterialBalance::query()->where([
                    'processing_order_id' => $receipt->processing_order_id,
                    'category_id' => $line->category_id,
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
     * Receive the good quantity onto the destination product using weighted average or overwrite.
     *
     * @return float  Change in the destination item's inventory value (total_price delta).
     */
    private function receiveGoodQty(
        ?Category $dest,
        float $qty,
        float $receiptUnitCost,
        float $receiptLayerTotal,
        string $costMode,
        ?float $manualSellPrice,
        string $refType,
        int $refId,
        string $label
    ): float {
        if ($qty <= 0 || ! $dest) {
            return 0.0;
        }

        $priorTotal = (float) ($dest->total_price ?? 0);

        if ($costMode === ProcessingReceiptLine::COST_MODE_WEIGHTED_AVERAGE) {
            // كمية فقط + إضافة قيمة طبقة الاستلام → متوسط مرجّح تلقائي.
            $this->ledger->recordInbound(
                $dest,
                InventoryMovementType::SubcontractReceiptGoodIn,
                $qty,
                $receiptUnitCost,
                $receiptLayerTotal,
                true,
                $refType,
                $refId,
                $label,
                null,
                auth()->user()?->name
            );

            $fresh = Category::query()->lockForUpdate()->findOrFail($dest->id);
            $newQty = (float) $fresh->quantity;
            $newTotal = (float) ($fresh->total_price ?? 0);
            // ضمان اتساق unit_price بعد الحفظ (sync داخل ledger قد يقرأ قبل الحفظ).
            if ($newQty > 0.0000001) {
                $fresh->unit_price = round($newTotal / $newQty, 4);
                $fresh->total_price = round($newTotal, 4);
            } else {
                $fresh->unit_price = round($receiptUnitCost, 4);
                $fresh->total_price = 0;
            }
            if ($manualSellPrice !== null && $manualSellPrice > 0.00001) {
                $fresh->category_price = round($manualSellPrice, 4);
                $fresh->sell_total_price = round($manualSellPrice * $newQty, 4);
            }
            $fresh->save();

            return round((float) $fresh->total_price - $priorTotal, 4);
        }

        // overwrite: كل الرصيد بسعر طبقة الاستلام الجديدة.
        $this->ledger->recordInbound(
            $dest,
            InventoryMovementType::SubcontractReceiptGoodIn,
            $qty,
            $receiptUnitCost,
            $receiptLayerTotal,
            false,
            $refType,
            $refId,
            $label,
            null,
            auth()->user()?->name
        );

        $fresh = Category::query()->lockForUpdate()->findOrFail($dest->id);
        $newQty = (float) $fresh->quantity;
        $fresh->unit_price = round($receiptUnitCost, 4);
        $fresh->total_price = round($receiptUnitCost * $newQty, 4);
        if ($manualSellPrice !== null && $manualSellPrice > 0.00001) {
            $fresh->category_price = round($manualSellPrice, 4);
            $fresh->sell_total_price = round($manualSellPrice * $newQty, 4);
        }
        $fresh->save();

        return round((float) $fresh->total_price - $priorTotal, 4);
    }

    private function writeCostHistory(
        Category $dest,
        ProcessingReceipt $receipt,
        ProcessingReceiptLine $line,
        string $costMode,
        float $goodQty,
        float $receiptUnitCost,
        float $receiptLayerTotal,
        float $wasteQty,
        float $wasteCost
    ): void {
        $oldQty = (float) ($line->prior_qty ?? 0);
        $oldUnit = (float) ($line->prior_unit_cost ?? 0);
        $oldTotal = round($oldQty * $oldUnit, 4);
        $newQty = (float) ($dest->quantity ?? 0);
        $newUnit = (float) ($line->resulting_unit_cost ?? CategoryInventoryCostService::averageCostForCategoryIssue((int) $dest->id));
        $newTotal = round((float) ($dest->total_price ?? ($newQty * $newUnit)), 4);

        $modeLabel = $costMode === ProcessingReceiptLine::COST_MODE_OVERWRITE
            ? 'استبدال تكلفة الرصيد'
            : 'متوسط مرجّح';

        CategoryCostHistory::query()->create([
            'category_id' => $dest->id,
            'source_type' => 'processing_receipt',
            'source_id' => $receipt->id,
            'source_line_id' => $line->id,
            'apply_mode' => $costMode,
            'old_qty' => $oldQty,
            'old_unit_cost' => $oldUnit,
            'old_total_cost' => $oldTotal,
            'receipt_qty' => $goodQty,
            'receipt_unit_cost' => $receiptUnitCost,
            'receipt_total_cost' => $receiptLayerTotal,
            'new_qty' => $newQty,
            'new_unit_cost' => $newUnit,
            'new_total_cost' => $newTotal,
            'waste_qty' => $wasteQty,
            'waste_cost' => $wasteCost,
            'notes' => sprintf(
                'إذن استلام %s — %s | كانت التكلفة %s وأصبحت %s',
                $receipt->receipt_number,
                $modeLabel,
                number_format($oldUnit, 4, '.', ''),
                number_format($newUnit, 4, '.', '')
            ),
            'created_by' => auth()->id(),
        ]);
    }
}
