<?php

namespace App\Services\Stock;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\TransactionType;
use App\Services\CategoryInventoryCostService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Posts user-created stock documents: ledger + formal stock_movements rows.
 */
class StockTransactionPostingService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryMovementLedgerService $ledger,
        private StockMovementJournalService $journal,
    ) {}

    public function post(array $payload): StockTransaction
    {
        return DB::transaction(function () use ($payload) {
            /** @var TransactionType $type */
            $type = TransactionType::query()->where('id', $payload['transaction_type_id'])->firstOrFail();
            if (! $type->is_active) {
                throw new \InvalidArgumentException('نوع الحركة غير مفعّل.');
            }

            $transactionNo = $this->numbers->generate((int) $type->id);

            $doc = StockTransaction::query()->create([
                'transaction_no' => $transactionNo,
                'transaction_type_id' => $type->id,
                'warehouse_id' => $payload['warehouse_id'] ?? null,
                'document_date' => $payload['document_date'],
                'notes' => $payload['notes'] ?? null,
                'created_by' => auth()->id(),
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
            ]);

            foreach ($payload['items'] as $row) {
                $item = StockTransactionItem::query()->create([
                    'stock_transaction_id' => $doc->id,
                    'product_id' => $row['product_id'],
                    'qty' => $row['qty'],
                    'price' => $row['price'] ?? 0,
                    'total' => $row['total'] ?? ((float) $row['qty'] * (float) ($row['price'] ?? 0)),
                    'to_product_id' => $row['to_product_id'] ?? null,
                    'qty_direction' => $row['qty_direction'] ?? null,
                ]);

                $this->applyLine($type->code, $doc, $item);
            }

            return $doc->load(['items.product', 'items.toProduct', 'type', 'warehouse']);
        });
    }

    private function applyLine(string $code, StockTransaction $doc, StockTransactionItem $item): void
    {
        $category = Category::query()->findOrFail($item->product_id);
        $warehouseId = $doc->warehouse_id ? (int) $doc->warehouse_id : null;

        if ($warehouseId && (int) $category->stock_id !== $warehouseId && $code !== 'WAREHOUSE_TRANSFER') {
            throw new \RuntimeException('الصنف لا يتبع المخزن المحدد في رأس المستند.');
        }

        $qty = (float) $item->qty;
        $price = (float) $item->price;
        $total = (float) $item->total;

        switch ($code) {
            case 'STOCK_OUT':
                $before = (float) $category->quantity;
                $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $this->ledger->recordOutbound(
                    $category,
                    InventoryMovementType::StockDocumentOut,
                    $qty,
                    $unit,
                    $total > 0 ? $total : null,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $after = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'out',
                    $qty,
                    $code,
                    $before,
                    $after,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $unit,
                    $total > 0 ? $total : null,
                );
                break;

            case 'SALES_RETURN':
                $before = (float) $category->quantity;
                $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $tc = $total > 0 ? $total : $qty * $unit;
                $this->ledger->recordInbound(
                    $category,
                    InventoryMovementType::StockDocumentSalesReturn,
                    $qty,
                    $unit,
                    $tc,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $after = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'in',
                    $qty,
                    $code,
                    $before,
                    $after,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $unit,
                    $tc,
                );
                break;

            case 'PURCHASE_RETURN':
                $before = (float) $category->quantity;
                $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $this->ledger->recordOutbound(
                    $category,
                    InventoryMovementType::StockDocumentPurchaseReturn,
                    $qty,
                    $unit,
                    $total > 0 ? $total : null,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $after = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'out',
                    $qty,
                    $code,
                    $before,
                    $after,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $unit,
                    $total > 0 ? $total : null,
                );
                break;

            case 'AMANAT_OUT':
                $before = (float) $category->quantity;
                $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $this->ledger->recordOutbound(
                    $category,
                    InventoryMovementType::StockDocumentAmanatOut,
                    $qty,
                    $unit,
                    $total > 0 ? $total : null,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $after = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'out',
                    $qty,
                    $code,
                    $before,
                    $after,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $unit,
                    $total > 0 ? $total : null,
                );
                break;

            case 'AMANAT_RETURN':
                $before = (float) $category->quantity;
                $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $tc = $total > 0 ? $total : $qty * $unit;
                $this->ledger->recordInbound(
                    $category,
                    InventoryMovementType::StockDocumentAmanatReturn,
                    $qty,
                    $unit,
                    $tc,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $after = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'in',
                    $qty,
                    $code,
                    $before,
                    $after,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $unit,
                    $tc,
                );
                break;

            case 'WAREHOUSE_TRANSFER':
                if (! $item->to_product_id) {
                    throw new \InvalidArgumentException('نقل المخزون يتطلب صف الوجهة to_product_id.');
                }
                $dest = Category::query()->findOrFail($item->to_product_id);
                if ((int) $dest->id === (int) $category->id) {
                    throw new \InvalidArgumentException('مصدر ووجهة النقل لا يمكن أن يكونا نفس صف الصنف.');
                }

                $ordered = collect([$category, $dest])->sortBy('id')->values();
                Category::query()->lockForUpdate()->findOrFail($ordered[0]->id);
                Category::query()->lockForUpdate()->findOrFail($ordered[1]->id);
                $category->refresh();
                $dest->refresh();

                $beforeSrc = (float) $category->quantity;
                $avg = CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                $tcOut = $qty * $avg;
                $this->ledger->recordOutbound(
                    $category,
                    InventoryMovementType::StockDocumentTransferOut,
                    $qty,
                    $avg,
                    $tcOut,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $afterSrc = (float) $category->fresh()->quantity;
                $this->journal->record(
                    (int) $category->id,
                    $category->stock_id ? (int) $category->stock_id : null,
                    'out',
                    $qty,
                    'WAREHOUSE_TRANSFER_OUT',
                    $beforeSrc,
                    $afterSrc,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $avg,
                    $tcOut,
                );

                $beforeDest = (float) $dest->quantity;
                $this->ledger->recordInbound(
                    $dest,
                    InventoryMovementType::StockDocumentTransferIn,
                    $qty,
                    $avg,
                    $tcOut,
                    true,
                    'stock_transaction',
                    (int) $doc->id,
                    $doc->notes,
                    null,
                    auth()->user()->name ?? null
                );
                $afterDest = (float) $dest->fresh()->quantity;
                $this->journal->record(
                    (int) $dest->id,
                    $dest->stock_id ? (int) $dest->stock_id : null,
                    'in',
                    $qty,
                    'WAREHOUSE_TRANSFER_IN',
                    $beforeDest,
                    $afterDest,
                    'stock_transaction',
                    (int) $doc->id,
                    (int) $doc->id,
                    $avg,
                    $tcOut,
                );
                break;

            case 'MANUAL_ADJUSTMENT':
                $dir = $item->qty_direction ?? 'in';
                if ($dir === 'out') {
                    $before = (float) $category->quantity;
                    $unit = $price > 0 ? $price : CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
                    $this->ledger->recordOutbound(
                        $category,
                        InventoryMovementType::ManualAdjustment,
                        $qty,
                        $unit,
                        $total > 0 ? $total : null,
                        true,
                        'stock_transaction',
                        (int) $doc->id,
                        $doc->notes,
                        null,
                        auth()->user()->name ?? null
                    );
                    $after = (float) $category->fresh()->quantity;
                    $this->journal->record(
                        (int) $category->id,
                        $category->stock_id ? (int) $category->stock_id : null,
                        'out',
                        $qty,
                        'MANUAL_ADJUSTMENT_OUT',
                        $before,
                        $after,
                        'stock_transaction',
                        (int) $doc->id,
                        (int) $doc->id,
                        $unit,
                        $total > 0 ? $total : null,
                    );
                } else {
                    $before = (float) $category->quantity;
                    $unit = $price > 0 ? $price : 0;
                    $tc = $total > 0 ? $total : $qty * $unit;
                    $this->ledger->recordInbound(
                        $category,
                        InventoryMovementType::ManualAdjustment,
                        $qty,
                        $unit,
                        $tc,
                        true,
                        'stock_transaction',
                        (int) $doc->id,
                        $doc->notes,
                        null,
                        auth()->user()->name ?? null
                    );
                    $after = (float) $category->fresh()->quantity;
                    $this->journal->record(
                        (int) $category->id,
                        $category->stock_id ? (int) $category->stock_id : null,
                        'in',
                        $qty,
                        'MANUAL_ADJUSTMENT_IN',
                        $before,
                        $after,
                        'stock_transaction',
                        (int) $doc->id,
                        (int) $doc->id,
                        $unit,
                        $tc,
                    );
                }
                break;

            default:
                throw new \InvalidArgumentException('نوع الحركة غير مدعوم للترحيل: '.$code);
        }
    }
}
