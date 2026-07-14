<?php

namespace App\Services\Purchases;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseInvoiceKind;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Stock\StockMovementJournalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceAccountingService
{
    public function __construct(
        private PurchaseInvoiceTypeResolver $typeResolver,
        private InventoryGlPostingService $glService,
        private InventoryMovementLedgerService $ledger,
        private StockMovementJournalService $movementJournal,
        private AccountLinkingService $accountLinking,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{lines_sum: float, inventory_gl: array<int, float>}
     */
    public function applyProductLines(Purchase $purchase, array $products, PurchaseInvoiceKind $kind): array
    {
        $linesSum = 0.0;
        $inventoryGlByAccount = [];
        $isInbound = $this->typeResolver->isInbound($kind);
        $movementType = $this->typeResolver->movementType($kind);
        $balanceType = $this->typeResolver->categoriesBalanceType($kind);
        $actor = auth()->user()->name ?? null;

        foreach ($products as $product) {
            $qty = $this->typeResolver->normalizeQuantity($kind, (float) $product['product_quantity']);
            $lineTotal = abs((float) $product['total']);
            $linesSum += $this->typeResolver->signedLineTotal($kind, $lineTotal);
            $declaredUnit = (float) $product['product_price'];
            $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost($lineTotal, $qty, $declaredUnit);

            $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product['product_name']);
            if (! $catId) {
                throw new \RuntimeException('تعذر ربط الصنف بالمخزن (مخزن مواد خام): '.$product['product_name']);
            }

            $invAcc = \App\Models\TreeAccount::resolveInventoryAccountForCategoryId((int) $catId);
            if ($invAcc && $kind !== PurchaseInvoiceKind::Amanat) {
                $inventoryGlByAccount[$invAcc->id] = ($inventoryGlByAccount[$invAcc->id] ?? 0) + $lineTotal;
            } elseif ($invAcc && $kind === PurchaseInvoiceKind::Amanat) {
                $inventoryGlByAccount[$invAcc->id] = ($inventoryGlByAccount[$invAcc->id] ?? 0) + $lineTotal;
            }

            DB::table('invoice_categories')->insert([
                'purchase_id' => $purchase->id,
                'category_id' => $catId,
                'product_name' => $product['product_name'],
                'product_quantity' => $isInbound ? $qty : -$qty,
                'product_unit' => $product['product_unit'],
                'product_price' => $product['product_price'],
                'total' => $this->typeResolver->signedLineTotal($kind, $lineTotal),
                'price_edited' => $product['price_edited'],
            ]);

            $category = Category::query()->findOrFail($catId);
            if ($isInbound) {
                $this->ledger->recordInbound(
                    $category,
                    $movementType,
                    $qty,
                    $effectiveUnit,
                    $lineTotal,
                    $kind !== PurchaseInvoiceKind::Amanat,
                    'purchase',
                    (int) $purchase->id,
                    $this->movementNote($kind, $purchase),
                    null,
                    $actor
                );
            } else {
                $this->ledger->recordOutbound(
                    $category,
                    $movementType,
                    $qty,
                    $effectiveUnit,
                    $lineTotal,
                    true,
                    'purchase',
                    (int) $purchase->id,
                    $this->movementNote($kind, $purchase),
                    null,
                    $actor
                );
            }

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($catId);
            $avgUnit = CategoryInventoryCostService::resolveReferenceUnitCost($catId);
            $signedQty = $isInbound ? $qty : -$qty;

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => $catId,
                'type' => $balanceType,
                'quantity' => $signedQty,
                'balance_before' => (float) DB::table('categories')->where('id', $catId)->value('quantity') - $signedQty,
                'balance_after' => (float) DB::table('categories')->where('id', $catId)->value('quantity'),
                'price' => $isInbound ? $effectiveUnit : $effectiveUnit * -1,
                'total_price' => $this->typeResolver->signedLineTotal($kind, $lineTotal),
                'unit_cost' => $avgUnit,
                'cost_total' => $this->typeResolver->signedLineTotal($kind, $lineTotal),
                'by' => $actor,
                'created_at' => now(),
            ]);

            DB::table('warehouse_ratings')->insert([
                'category_id' => $catId,
                'price' => $isInbound ? $effectiveUnit : $effectiveUnit * -1,
                'quantity' => $signedQty,
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $signedQty,
                'created_at' => now(),
            ]);
        }

        return [
            'lines_sum' => $linesSum,
            'inventory_gl' => $inventoryGlByAccount,
        ];
    }

    /**
     * Edit path: apply only the net stock delta per category instead of full reverse + re-apply.
     * Avoids failing when part of the original receipt quantity was already consumed.
     *
     * @param  array<int, array<string, mixed>>  $newProducts
     * @return array{lines_sum: float, inventory_gl: array<int, float>, stock_warnings: array<int, string>}
     */
    public function reconcileProductLinesOnEdit(
        Purchase $purchase,
        Collection $oldLines,
        PurchaseInvoiceKind $oldKind,
        array $newProducts,
        PurchaseInvoiceKind $newKind,
    ): array {
        $oldSignedByCategory = $this->aggregateSignedQuantitiesByCategory($oldLines, $oldKind, true);
        $newSignedByCategory = $this->aggregateSignedQuantitiesByCategory($newProducts, $newKind, false);

        $linesSum = 0.0;
        $inventoryGlByAccount = [];
        $isInbound = $this->typeResolver->isInbound($newKind);
        $actor = auth()->user()->name ?? null;

        foreach ($newProducts as $product) {
            $qty = $this->typeResolver->normalizeQuantity($newKind, (float) $product['product_quantity']);
            $lineTotal = abs((float) $product['total']);
            $linesSum += $this->typeResolver->signedLineTotal($newKind, $lineTotal);

            $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product['product_name']);
            if (! $catId) {
                throw new \RuntimeException('تعذر ربط الصنف بالمخزن (مخزن مواد خام): '.$product['product_name']);
            }

            $invAcc = \App\Models\TreeAccount::resolveInventoryAccountForCategoryId((int) $catId);
            if ($invAcc) {
                $inventoryGlByAccount[$invAcc->id] = ($inventoryGlByAccount[$invAcc->id] ?? 0) + $lineTotal;
            }

            DB::table('invoice_categories')->insert([
                'purchase_id' => $purchase->id,
                'category_id' => $catId,
                'product_name' => $product['product_name'],
                'product_quantity' => $isInbound ? $qty : -$qty,
                'product_unit' => $product['product_unit'],
                'product_price' => $product['product_price'],
                'total' => $this->typeResolver->signedLineTotal($newKind, $lineTotal),
                'price_edited' => $product['price_edited'],
            ]);
        }

        $categoryIds = array_unique(array_merge(
            array_keys($oldSignedByCategory),
            array_keys($newSignedByCategory),
        ));

        $oldWasInbound = $this->typeResolver->isInbound($oldKind);
        $newIsInbound = $this->typeResolver->isInbound($newKind);
        $stockWarnings = [];

        foreach ($categoryIds as $catId) {
            $oldSigned = (string) ($oldSignedByCategory[$catId] ?? '0');
            $newSigned = (string) ($newSignedByCategory[$catId] ?? '0');
            $delta = bcsub($newSigned, $oldSigned, 6);

            if (bccomp($delta, '0', 6) === 0) {
                continue;
            }

            $category = Category::query()->findOrFail($catId);
            $absDelta = (string) abs((float) $delta);
            $beforeQty = (float) ($category->quantity ?? 0);

            if (bccomp($delta, '0', 6) > 0) {
                $movementType = ! $oldWasInbound
                    ? $this->typeResolver->reversalMovementType($oldKind)
                    : $this->typeResolver->movementType($newKind);
                [$unitCost, $lineTotal] = $this->resolveEditDeltaCost($newProducts, $newKind, $catId, (float) $absDelta);

                $this->ledger->recordInbound(
                    $category,
                    $movementType,
                    $absDelta,
                    $unitCost,
                    $lineTotal,
                    $newKind !== PurchaseInvoiceKind::Amanat,
                    'purchase',
                    (int) $purchase->id,
                    'تعديل فاتورة مشتريات — '.$purchase->invoice_number,
                    null,
                    $actor
                );

                $direction = 'in';
            } else {
                $movementType = $newIsInbound
                    ? $this->typeResolver->reversalMovementType($oldKind)
                    : $this->typeResolver->movementType($newKind);
                $unitCost = CategoryInventoryCostService::averageCostForCategoryIssue((int) $catId);
                $requestedQty = (float) $absDelta;
                $availableQty = max(0.0, (float) ($category->quantity ?? 0));
                $actualQty = min($requestedQty, $availableQty);
                $lineTotal = $requestedQty > 0.000001
                    ? ((float) $absDelta * (float) $unitCost) * ($actualQty / $requestedQty)
                    : 0.0;
                $movementNote = 'تعديل فاتورة مشتريات — '.$purchase->invoice_number;

                if ($actualQty <= 0.000001) {
                    $stockWarnings[] = 'تم حفظ التعديل دون خصم مخزون للصنف #'.$catId
                        .' — الكمية المتاحة صفر (تم صرف جزء من الكمية سابقاً).';
                    continue;
                }

                if ($actualQty + 0.000001 < $requestedQty) {
                    $stockWarnings[] = 'تم حفظ التعديل مع خصم جزئي للصنف #'.$catId
                        .': خُصم '.$actualQty.' فقط من أصل '.$requestedQty
                        .' لأن جزءاً من الكمية مُصرف مسبقاً من المخزون.';
                    $movementNote .= ' (خصم جزئي '.$actualQty.'/'.$requestedQty.')';
                }

                $this->ledger->recordOutbound(
                    $category,
                    $movementType,
                    $actualQty,
                    $unitCost,
                    $lineTotal,
                    $oldKind !== PurchaseInvoiceKind::Amanat,
                    'purchase',
                    (int) $purchase->id,
                    $movementNote,
                    null,
                    $actor
                );

                $direction = 'out';
                $absDelta = (string) $actualQty;
                $delta = bcsub('0', $absDelta, 6);
            }

            $afterQty = (float) Category::query()->where('id', $catId)->value('quantity');
            $stockId = Category::query()->where('id', $catId)->value('stock_id');
            $this->movementJournal->record(
                (int) $catId,
                $stockId ? (int) $stockId : null,
                $direction,
                (float) $absDelta,
                $movementType->value,
                $beforeQty,
                $afterQty,
                'purchase',
                (int) $purchase->id,
                null,
                $unitCost,
                $lineTotal,
            );

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $catId);
            $avgUnit = CategoryInventoryCostService::resolveReferenceUnitCost((int) $catId);

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => $catId,
                'type' => 'تعديل فواتير مشتريات',
                'quantity' => (float) $delta,
                'balance_before' => $beforeQty,
                'balance_after' => $afterQty,
                'price' => bccomp($delta, '0', 6) > 0 ? $unitCost : $unitCost * -1,
                'total_price' => bccomp($delta, '0', 6) > 0 ? $lineTotal : -$lineTotal,
                'unit_cost' => $avgUnit,
                'cost_total' => bccomp($delta, '0', 6) > 0 ? $lineTotal : -$lineTotal,
                'by' => $actor,
                'created_at' => now(),
            ]);

            DB::table('warehouse_ratings')->insert([
                'category_id' => $catId,
                'price' => bccomp($delta, '0', 6) > 0 ? $unitCost : $unitCost * -1,
                'quantity' => (float) $delta,
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => (float) $delta,
                'created_at' => now(),
            ]);
        }

        return [
            'lines_sum' => $linesSum,
            'inventory_gl' => $inventoryGlByAccount,
            'stock_warnings' => $stockWarnings,
        ];
    }

    /**
     * @param  Collection<int, object>|array<int, array<string, mixed>>  $lines
     * @return array<int, string>
     */
    private function aggregateSignedQuantitiesByCategory(Collection|array $lines, PurchaseInvoiceKind $kind, bool $fromPersistedLines): array
    {
        $map = [];
        $isInbound = $this->typeResolver->isInbound($kind);

        foreach ($lines as $line) {
            if ($fromPersistedLines) {
                $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($line, $line->product_name);
                if (! $catId) {
                    continue;
                }
                $signed = (string) ($line->product_quantity ?? '0');
            } else {
                $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($line, $line['product_name']);
                if (! $catId) {
                    continue;
                }
                $qty = $this->typeResolver->normalizeQuantity($kind, (float) $line['product_quantity']);
                $signed = $isInbound ? (string) $qty : bcsub('0', (string) $qty, 6);
            }

            $map[$catId] = bcadd($map[$catId] ?? '0', $signed, 6);
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array{0: float, 1: float}
     */
    private function resolveEditDeltaCost(array $products, PurchaseInvoiceKind $kind, int $categoryId, float $deltaQty): array
    {
        $matchedTotal = 0.0;
        $matchedQty = 0.0;

        foreach ($products as $product) {
            $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product['product_name']);
            if ((int) $catId !== $categoryId) {
                continue;
            }

            $qty = $this->typeResolver->normalizeQuantity($kind, (float) $product['product_quantity']);
            $lineTotal = abs((float) $product['total']);
            $matchedQty += $qty;
            $matchedTotal += $lineTotal;
        }

        if ($matchedQty <= 0.000001) {
            $unit = CategoryInventoryCostService::averageCostForCategoryIssue($categoryId);

            return [$unit, $deltaQty * $unit];
        }

        $unit = CategoryInventoryCostService::purchaseLineUnitCost($matchedTotal, $matchedQty, $matchedTotal / $matchedQty);

        return [$unit, $deltaQty * $unit];
    }

    /**
     * @return array{stock_warnings: list<string>}
     */
    public function reverseProductLines(Purchase $purchase, Collection $lines, PurchaseInvoiceKind $kind): array
    {
        $wasInbound = $this->typeResolver->isInbound($kind);
        $reversalType = $this->typeResolver->reversalMovementType($kind);
        $actor = auth()->user()->name ?? null;
        $stockWarnings = [];

        foreach ($lines as $product) {
            $requestedQty = abs((float) $product->product_quantity);
            if ($requestedQty <= 0.000001) {
                continue;
            }
            $requestedLineTotal = abs((float) $product->total);
            $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost(
                $requestedLineTotal,
                $requestedQty,
                (float) $product->product_price
            );

            $catId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product->product_name);
            if (! $catId) {
                throw new \RuntimeException('تعذر ربط الصنف عند العكس: '.$product->product_name);
            }

            $category = Category::query()->findOrFail($catId);
            $qty = $requestedQty;
            $lineTotal = $requestedLineTotal;
            $movementNote = 'حذف فاتورة مشتريات — '.$purchase->invoice_number;

            if ($wasInbound) {
                $availableQty = (float) ($category->quantity ?? 0);
                if ($availableQty + 0.000001 < $requestedQty) {
                    $stockWarnings[] = 'تم حذف الفاتورة مع خصم سالب للصنف #'.$catId
                        .': الرصيد '.$availableQty.' وأُخصم '.$requestedQty
                        .' (الرصيد بعد الحذف: '.($availableQty - $requestedQty).').';
                    $movementNote .= ' (خصم سالب)';
                }

                $this->ledger->recordOutbound(
                    $category,
                    $reversalType,
                    $qty,
                    $effectiveUnit,
                    $lineTotal,
                    $kind !== PurchaseInvoiceKind::Amanat,
                    'purchase_invoice_delete_reversal',
                    (int) $purchase->id,
                    $movementNote,
                    null,
                    $actor,
                    true
                );
            } else {
                $this->ledger->recordInbound(
                    $category,
                    $reversalType,
                    $qty,
                    $effectiveUnit,
                    $lineTotal,
                    true,
                    'purchase_invoice_delete_reversal',
                    (int) $purchase->id,
                    $movementNote,
                    null,
                    $actor
                );
            }

            $afterQty = (float) Category::query()->where('id', $catId)->value('quantity');
            $signedQty = $wasInbound ? -$qty : $qty;
            $beforeQty = $afterQty - $signedQty;

            $stockId = Category::query()->where('id', $catId)->value('stock_id');
            $this->movementJournal->record(
                (int) $catId,
                $stockId ? (int) $stockId : null,
                $wasInbound ? 'out' : 'in',
                $qty,
                $reversalType->value,
                $beforeQty,
                $afterQty,
                'purchase',
                (int) $purchase->id,
                null,
                $effectiveUnit,
                $lineTotal,
            );

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($catId);

            DB::table('categories_balance')->insert([
                'invoice_number' => $purchase->invoice_number,
                'category_id' => $catId,
                'type' => 'حذف فاتورة مشتريات',
                'quantity' => $signedQty,
                'balance_before' => $beforeQty,
                'balance_after' => $afterQty,
                'price' => $wasInbound ? $effectiveUnit * -1 : $effectiveUnit,
                'total_price' => $wasInbound ? -$lineTotal : $lineTotal,
                'unit_cost' => $effectiveUnit,
                'cost_total' => $wasInbound ? -$lineTotal : $lineTotal,
                'by' => $actor,
                'created_at' => now(),
            ]);

            DB::table('warehouse_ratings')->insert([
                'category_id' => $catId,
                'price' => $wasInbound ? $effectiveUnit * -1 : $effectiveUnit,
                'quantity' => $signedQty,
                'ref' => $purchase->invoice_number,
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $signedQty,
                'created_at' => now(),
            ]);
        }

        return ['stock_warnings' => $stockWarnings];
    }

    public function adjustSupplierBalances(
        Supplier $supplier,
        PurchaseInvoiceKind $kind,
        float $dueAmount,
        float $grandTotal,
        int $purchaseId,
        ?Supplier $previousSupplier = null,
        float $previousDueAmount = 0.0,
        ?PurchaseInvoiceKind $previousKind = null,
        float $previousGrandTotal = 0.0,
    ): void {
        $previousGrand = $previousGrandTotal > 0.00001
            ? $previousGrandTotal
            : abs($previousDueAmount);

        if ($previousSupplier !== null && $previousKind !== null
            && $this->typeResolver->affectsSupplierBalance($previousKind)) {
            $oldDelta = $this->typeResolver->supplierBalanceDelta(
                $previousKind,
                $previousDueAmount,
                $previousGrand
            );

            if (abs($oldDelta) > 0.00001 && $previousSupplier->id !== $supplier->id) {
                $this->applySupplierDelta(
                    $previousSupplier,
                    -$oldDelta,
                    $purchaseId,
                    'تعديل فاتورة — إزالة ذمة المورد السابق'
                );
            }
        }

        if (! $this->typeResolver->affectsSupplierBalance($kind)) {
            if ($previousSupplier !== null && $previousKind !== null
                && $previousSupplier->id === $supplier->id
                && $this->typeResolver->affectsSupplierBalance($previousKind)) {
                $oldDelta = $this->typeResolver->supplierBalanceDelta(
                    $previousKind,
                    $previousDueAmount,
                    $previousGrand
                );
                if (abs($oldDelta) > 0.00001) {
                    $this->applySupplierDelta(
                        $supplier,
                        -$oldDelta,
                        $purchaseId,
                        'تعديل فاتورة — إزالة ذمة سابقة'
                    );
                }
            }

            return;
        }

        $newDelta = $this->typeResolver->supplierBalanceDelta($kind, $dueAmount, $grandTotal);

        if ($previousSupplier !== null && $previousKind !== null
            && $previousSupplier->id === $supplier->id
            && $this->typeResolver->affectsSupplierBalance($previousKind)) {
            $oldDelta = $this->typeResolver->supplierBalanceDelta(
                $previousKind,
                $previousDueAmount,
                $previousGrand
            );
            $netDelta = $newDelta - $oldDelta;
            if (abs($netDelta) > 0.00001) {
                $this->applySupplierDelta($supplier, $netDelta, $purchaseId, 'تعديل فاتورة مشتريات');
            }

            return;
        }

        if (abs($newDelta) > 0.00001) {
            $this->applySupplierDelta($supplier, $newDelta, $purchaseId, 'فاتورة مشتريات');
        }
    }

    /**
     * @param  array<int, float>  $inventoryGlByAccount
     */
    public function postGlForPurchase(
        Purchase $purchase,
        Supplier $supplier,
        array $inventoryGlByAccount,
        float $transport,
        float $productTotalAbs,
        PurchaseInvoiceKind $kind,
        ?int $userId = null,
    ): void {
        $description = $this->glDescription($kind, $purchase);
        $productTotalAbs = abs($productTotalAbs);
        $transport = max(0, $transport);

        if ($kind === PurchaseInvoiceKind::SalesReturn) {
            if ($productTotalAbs > 0.00001) {
                $this->glService->postSalesReturnInventoryRestoreByWarehouse(
                    $inventoryGlByAccount,
                    $description,
                    $userId
                );
            }

            return;
        }

        if ($kind === PurchaseInvoiceKind::Amanat) {
            // حركة مخزنية فقط — بدون قيود GL حتى يُربط حساب أمانات مستقل
            return;
        }

        $shippingCompany = $purchase->shipping_company_id
            ? ShippingCompany::query()->find($purchase->shipping_company_id)
            : null;

        if ($kind === PurchaseInvoiceKind::PurchaseReturn) {
            if ($productTotalAbs + $transport > 0.00001) {
                $this->glService->reversePurchaseReceiptSplitByAccountsWithFreightPayable(
                    $inventoryGlByAccount,
                    $transport,
                    $supplier,
                    $shippingCompany,
                    $description,
                    $userId
                );
            }

            return;
        }

        if ($productTotalAbs + $transport > 0.00001) {
            $this->glService->postPurchaseReceiptSplitByAccountsWithFreightPayable(
                $inventoryGlByAccount,
                $transport,
                $supplier,
                $shippingCompany,
                $description,
                $userId
            );
        }
    }

    /**
     * @param  array<int, float>  $inventoryGlByAccount
     */
    public function reverseGlForPurchase(
        Purchase $purchase,
        Supplier $supplier,
        array $inventoryGlByAccount,
        float $transport,
        float $productTotalAbs,
        PurchaseInvoiceKind $kind,
        ?int $userId = null,
        string $actionLabel = 'عكس',
    ): void {
        $description = $actionLabel.' — '.$this->glDescription($kind, $purchase);
        $productTotalAbs = abs($productTotalAbs);
        $transport = max(0, (float) $transport);
        $shippingCompany = $purchase->shipping_company_id
            ? ShippingCompany::query()->find($purchase->shipping_company_id)
            : null;

        if ($kind === PurchaseInvoiceKind::SalesReturn) {
            if ($productTotalAbs > 0.00001) {
                $this->glService->reverseSalesReturnInventoryRestoreByWarehouse(
                    $inventoryGlByAccount,
                    $description,
                    $userId
                );
            }

            return;
        }

        if ($kind === PurchaseInvoiceKind::Amanat) {
            return;
        }

        if ($kind === PurchaseInvoiceKind::PurchaseReturn) {
            if ($productTotalAbs + $transport > 0.00001) {
                $this->glService->postPurchaseReceiptSplitByAccountsWithFreightPayable(
                    $inventoryGlByAccount,
                    $transport,
                    $supplier,
                    $shippingCompany,
                    $description,
                    $userId
                );
            }

            return;
        }

        if ($productTotalAbs + $transport > 0.00001) {
            $this->glService->reversePurchaseReceiptSplitByAccountsWithFreightPayable(
                $inventoryGlByAccount,
                $transport,
                $supplier,
                $shippingCompany,
                $description,
                $userId
            );
        }
    }

    private function applySupplierDelta(Supplier $supplier, float $delta, int $purchaseId, string $note): void
    {
        if (abs($delta) <= 0.00001) {
            return;
        }

        $supplier->last_balance = $supplier->balance;
        $supplier->balance += $delta;
        $supplier->save();

        DB::table('supplier_balance')->insert([
            'invoice_id' => $purchaseId,
            'balance_before' => $supplier->last_balance,
            'balance_after' => $supplier->balance,
            'user_id' => auth()->id(),
        ]);
    }

    private function movementNote(PurchaseInvoiceKind $kind, Purchase $purchase): string
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => 'مرتجع مبيعات — فاتورة '.$purchase->invoice_number,
            PurchaseInvoiceKind::Amanat => 'امانات — فاتورة '.$purchase->invoice_number,
            PurchaseInvoiceKind::PurchaseReturn => 'مرتجع مشتريات — فاتورة '.$purchase->invoice_number,
            default => 'استلام مشتريات — فاتورة '.$purchase->invoice_number,
        };
    }

    private function glDescription(PurchaseInvoiceKind $kind, Purchase $purchase): string
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => 'مرتجع مبيعات — فاتورة '.$purchase->invoice_number,
            PurchaseInvoiceKind::Amanat => 'امانات — فاتورة '.$purchase->invoice_number,
            PurchaseInvoiceKind::PurchaseReturn => 'مرتجع مشتريات — فاتورة '.$purchase->invoice_number,
            default => 'استلام مشتريات — فاتورة '.$purchase->invoice_number,
        };
    }
}
