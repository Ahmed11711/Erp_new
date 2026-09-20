<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\Production;
use App\Models\StockTransaction;
use App\Models\TransactionType;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Documents\DocumentNumberService;
use App\Services\Stock\StockMovementJournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * صرف مستلزمات تشغيل لقسم إنتاج: خصم المخزون + قيد
 * مدين مصروفات صناعية غير مباشرة / دائن مخزون مستلزمات التشغيل.
 */
class OperatingSuppliesIssueService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryMovementLedgerService $ledger,
        private StockMovementJournalService $journal,
        private InventoryGlPostingService $gl,
    ) {}

    /**
     * @param  array{category_id:int, qty:float, production_id?:int|null, notes?:string|null, document_date?:string|null}  $payload
     */
    public function issue(array $payload): StockTransaction
    {
        $categoryId = (int) $payload['category_id'];
        $qty = (float) $payload['qty'];
        $productionId = isset($payload['production_id']) ? (int) $payload['production_id'] : null;
        $notes = trim((string) ($payload['notes'] ?? ''));
        $documentDate = $payload['document_date'] ?? now()->toDateString();

        if ($qty <= 0.0000001) {
            throw new \InvalidArgumentException('الكمية يجب أن تكون أكبر من صفر.');
        }

        $stock = OperatingSuppliesWarehouseResolver::ensureStock();
        $category = Category::query()->findOrFail($categoryId);

        if (! OperatingSuppliesWarehouseResolver::isOperatingSuppliesWarehouse(
            $stock,
            (string) ($category->warehouse ?? '')
        ) && (int) ($category->stock_id ?? 0) !== (int) $stock->id) {
            throw new \RuntimeException('الصنف ليس من مخزن مستلزمات التشغيل.');
        }

        if ((float) $category->quantity + 0.0000001 < $qty) {
            throw new \RuntimeException('الكمية المتاحة في المخزن غير كافية.');
        }

        if ($productionId) {
            if (! Production::query()->whereKey($productionId)->exists()) {
                throw new \InvalidArgumentException('قسم الإنتاج غير موجود.');
            }
        }

        $type = TransactionType::query()->where('code', 'STOCK_OUT')->first();
        if (! $type) {
            throw new \RuntimeException('نوع مستند STOCK_OUT غير مهيأ. شغّل StockDocumentsFoundationSeeder.');
        }

        return DB::transaction(function () use (
            $category,
            $qty,
            $productionId,
            $notes,
            $documentDate,
            $stock,
            $type
        ) {
            if ($productionId && (int) ($category->production_id ?? 0) !== $productionId) {
                $category->production_id = $productionId;
                $category->save();
            }

            $unit = CategoryInventoryCostService::averageCostForCategoryIssue((int) $category->id);
            $total = round($qty * $unit, 4);
            $productionLabel = $productionId
                ? (string) (Production::query()->whereKey($productionId)->value('production_line') ?? ('#'.$productionId))
                : null;
            $descParts = ['صرف مستلزمات تشغيل'];
            if ($productionLabel) {
                $descParts[] = 'لقسم '.$productionLabel;
            }
            $descParts[] = $category->category_name;
            $description = implode(' — ', $descParts);
            if ($notes !== '') {
                $description .= ' ('.$notes.')';
            }

            $transactionNo = $this->numbers->generate((int) $type->id);
            $docData = [
                'transaction_no' => $transactionNo,
                'transaction_type_id' => $type->id,
                'warehouse_id' => (int) $stock->id,
                'document_date' => $documentDate,
                'notes' => $notes !== '' ? $notes : $description,
                'created_by' => auth()->id(),
                'reference_type' => 'operating_supplies_issue',
                'reference_id' => (int) $category->id,
            ];
            if (Schema::hasColumn('stock_transactions', 'production_id')) {
                $docData['production_id'] = $productionId ?: null;
            }

            $doc = StockTransaction::query()->create($docData);
            $item = $doc->items()->create([
                'product_id' => (int) $category->id,
                'qty' => $qty,
                'price' => $unit,
                'total' => $total,
            ]);

            $before = (float) $category->quantity;
            $this->ledger->recordOutbound(
                $category,
                InventoryMovementType::StockDocumentOut,
                $qty,
                $unit,
                $total,
                true,
                'stock_transaction',
                (int) $doc->id,
                $description,
                null,
                auth()->user()->name ?? null
            );
            $after = (float) $category->fresh()->quantity;

            $this->journal->record(
                (int) $category->id,
                (int) $stock->id,
                'out',
                $qty,
                'STOCK_OUT',
                $before,
                $after,
                'stock_transaction',
                (int) $doc->id,
                (int) $doc->id,
                $unit,
                $total,
            );

            DB::table('categories_balance')->insert([
                'invoice_number' => $transactionNo,
                'category_id' => (int) $category->id,
                'type' => 'صرف مستلزمات تشغيل',
                'quantity' => -$qty,
                'balance_before' => $before,
                'balance_after' => $after,
                'price' => -$unit,
                'total_price' => -$total,
                'unit_cost' => $unit,
                'cost_total' => -$total,
                'by' => auth()->user()->name ?? null,
                'created_at' => now(),
            ]);

            if ($total > 0.00001) {
                $this->gl->postOperatingSuppliesIssue(
                    $total,
                    $description,
                    auth()->id()
                );
            }

            unset($item);

            return $doc->load(['items.product', 'type', 'warehouse']);
        });
    }
}
