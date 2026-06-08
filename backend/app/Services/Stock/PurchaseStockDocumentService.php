<?php

namespace App\Services\Stock;

use App\Models\Category;
use App\Models\Purchase;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

/**
 * Links legacy purchase invoices (purchases + invoice_categories) to formal stock documents.
 * Writes stock_transaction + items + stock_movements using reconstructed before/after quantities.
 */
class PurchaseStockDocumentService
{
    public function __construct(
        private StockMovementJournalService $journal,
    ) {}

    public function syncPurchaseDocument(Purchase $purchase): void
    {
        if (! $purchase->invoice_no) {
            return;
        }

        DB::transaction(function () use ($purchase) {
            // رقم transaction_no فريد على مستوى الجدول؛ مراجعة مشتريات جديدة تُعيد استخدام نفس invoice_no
            // بينما سند المخزون القديم ما زال مربوطاً بصف purchase سابق في نفس السلسلة → تعارض 1062.
            $mainId = $purchase->ref ? (int) $purchase->ref : (int) $purchase->id;
            $chainPurchaseIds = Purchase::query()
                ->where(function ($q) use ($mainId) {
                    $q->where('id', $mainId)->orWhere('ref', $mainId);
                })
                ->pluck('id')
                ->all();

            StockTransaction::query()
                ->where('reference_type', 'purchase')
                ->whereIn('reference_id', $chainPurchaseIds)
                ->delete();

            $lines = DB::table('invoice_categories')->where('purchase_id', $purchase->id)->orderBy('id')->get();
            if ($lines->isEmpty()) {
                return;
            }

            $purchaseType = TransactionType::query()->where('code', 'PURCHASE_ADD')->firstOrFail();
            $typeId = (int) $purchaseType->id;

            $warehouseId = null;
            foreach ($lines as $line) {
                if (! $line->category_id) {
                    continue;
                }
                $c = Category::query()->find((int) $line->category_id);
                if ($c && $c->stock_id) {
                    $warehouseId = (int) $c->stock_id;
                    break;
                }
            }

            $doc = StockTransaction::query()->create([
                'transaction_no' => $purchase->invoice_no,
                'transaction_type_id' => $typeId,
                'warehouse_id' => $warehouseId,
                'document_date' => $purchase->receipt_date,
                'notes' => $purchase->notes,
                'created_by' => auth()->id(),
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);

            $grouped = [];
            foreach ($lines as $line) {
                $cid = (int) $line->category_id;
                if (! isset($grouped[$cid])) {
                    $grouped[$cid] = [];
                }
                $grouped[$cid][] = $line;

                StockTransactionItem::query()->create([
                    'stock_transaction_id' => $doc->id,
                    'product_id' => $cid,
                    'qty' => $line->product_quantity,
                    'price' => $line->product_price,
                    'total' => $line->total,
                ]);
            }

            foreach ($grouped as $categoryId => $catLines) {
                $cat = Category::query()->find($categoryId);
                if (! $cat) {
                    continue;
                }
                $final = (string) ($cat->quantity ?? '0');
                $sum = '0';
                foreach ($catLines as $l) {
                    $sum = bcadd($sum, (string) $l->product_quantity, 6);
                }
                $running = bcsub($final, $sum, 6);
                foreach ($catLines as $l) {
                    $qty = (string) $l->product_quantity;
                    $before = $running;
                    $after = bcadd($running, $qty, 6);
                    $this->journal->record(
                        $categoryId,
                        $cat->stock_id ? (int) $cat->stock_id : null,
                        'in',
                        (float) $qty,
                        'PURCHASE_ADD',
                        (float) $before,
                        (float) $after,
                        'purchase',
                        (int) $purchase->id,
                        $doc->id,
                        null,
                        null,
                    );
                    $running = $after;
                }
            }
        });
    }
}
