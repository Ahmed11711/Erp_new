<?php

namespace App\Services\Manufacturing;

use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\ConfirmedManfucture;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a confirmed manufacture order and reverses inventory + GL as if it never ran.
 */
final class ManufacturingOrderReversalService
{
    private const BALANCE_TYPE = 'تصنيع';
    private const REVERSAL_BALANCE_TYPE = 'إلغاء تصنيع';

    /** @var list<string> */
    private const WIP_TRANSFER_REF_TYPES = [
        'wip_to_finished_split',
        'wip_to_finished_merge',
        'wip_to_finished_promotion',
    ];

    public function __construct(
        private InventoryMovementLedgerService $ledger,
        private AccountingService $accountingService,
    ) {
    }

    /**
     * @return array{message: string, order_id: int, deleted_by: int}
     */
    public function deleteAndReverse(int $orderId, int $deletedByUserId): array
    {
        return DB::transaction(function () use ($orderId, $deletedByUserId) {
            $order = ConfirmedManfucture::query()
                ->lockForUpdate()
                ->whereNull('deleted_at')
                ->find($orderId);

            if (! $order) {
                throw new \RuntimeException('أمر التصنيع غير موجود أو تم حذفه مسبقاً.');
            }

            $actor = User::query()->find($deletedByUserId)?->name ?? 'النظام';

            $this->reverseCategoriesBalanceRows($order, $actor);
            $this->reverseWipTransfers($order, $deletedByUserId, $actor);
            $this->reverseGlEntries($order, $deletedByUserId);
            $this->appendAuditReversalMovements($orderId, $actor);

            $order->deleted_at = now();
            $order->deleted_by = $deletedByUserId;
            $order->save();

            return [
                'message' => 'تم حذف أمر التصنيع وعكس حركات المخزون بنجاح.',
                'order_id' => $orderId,
                'deleted_by' => $deletedByUserId,
            ];
        });
    }

    private function reverseCategoriesBalanceRows(ConfirmedManfucture $order, string $actor): void
    {
        $rows = DB::table('categories_balance')
            ->where('invoice_number', $order->id)
            ->where('type', self::BALANCE_TYPE)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $category = Category::query()->lockForUpdate()->find((int) $row->category_id);
            if (! $category) {
                continue;
            }

            $qty = (float) $row->quantity;
            $costTotal = (float) ($row->cost_total ?? $row->total_price ?? 0);
            $unitCost = (float) ($row->unit_cost ?? $row->price ?? 0);
            $wasOutbound = (float) $row->balance_after < (float) $row->balance_before;

            $balanceBefore = (float) $category->quantity;

            if ($wasOutbound) {
                if ($qty <= 0) {
                    continue;
                }
                $category->quantity = (float) $category->quantity + $qty;
                $category->total_price = round(((float) ($category->total_price ?? 0)) + $costTotal, 4);
                $this->restoreWarehouseRatingLayer((int) $category->id, $qty, $unitCost);
            } else {
                $category->quantity = (float) $category->quantity - $qty;
                $category->total_price = round((float) ($category->total_price ?? 0) - $costTotal, 4);
                $sellDelta = (float) ($category->category_price ?? 0) * $qty;
                $category->sell_total_price = round((float) ($category->sell_total_price ?? 0) - $sellDelta, 4);
            }

            $category->save();
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $category->id);

            DB::table('categories_balance')->insert([
                'invoice_number' => $order->id,
                'category_id' => $category->id,
                'type' => self::REVERSAL_BALANCE_TYPE,
                'quantity' => $qty,
                'balance_before' => $balanceBefore,
                'balance_after' => (float) $category->fresh()->quantity,
                'price' => $unitCost,
                'total_price' => $wasOutbound ? $costTotal : -$costTotal,
                'unit_cost' => $unitCost,
                'cost_total' => $wasOutbound ? $costTotal : -$costTotal,
                'by' => $actor,
                'created_at' => now(),
            ]);
        }
    }

    private function restoreWarehouseRatingLayer(int $categoryId, float $qty, float $unitCost): void
    {
        if ($qty <= 0.0000001) {
            return;
        }

        $existing = DB::table('warehouse_ratings')
            ->where('category_id', $categoryId)
            ->where('price', $unitCost)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            DB::table('warehouse_ratings')
                ->where('id', $existing->id)
                ->increment('quantity', $qty);

            return;
        }

        DB::table('warehouse_ratings')->insert([
            'category_id' => $categoryId,
            'price' => $unitCost,
            'quantity' => $qty,
            'fixed_quantity' => $qty,
            'ref' => 'manufacture_reversal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reverseWipTransfers(ConfirmedManfucture $order, int $userId, string $actor): void
    {
        $meta = is_array($order->completion_meta) ? $order->completion_meta : [];
        $strategy = (string) ($meta['strategy'] ?? '');

        if ($strategy === 'full_same' || ($strategy === '' && $this->looksLikeFullWipPromotion($order))) {
            $this->reverseFullWipPromotion($order, $userId, $actor);

            return;
        }

        if ($strategy === 'partial_new') {
            $this->reversePartialNewWip($order, $meta, $userId, $actor);

            return;
        }

        if ($strategy === 'partial_merge') {
            $this->reversePartialMergeWip($order, $meta, $userId, $actor);

            return;
        }

        $movements = InventoryMovement::query()
            ->where('reference_id', $order->id)
            ->whereIn('reference_type', ['wip_to_finished_split', 'wip_to_finished_merge'])
            ->orderBy('id')
            ->get();

        if ($movements->isNotEmpty()) {
            $this->reverseTransferMovements($movements, $order->id, $actor);
        }
    }

    private function looksLikeFullWipPromotion(ConfirmedManfucture $order): bool
    {
        $item = Item::query()->find($order->product_id);
        if (! $item || $item->resolvedProductType() !== ProductType::Finished) {
            return false;
        }

        return InventoryMovement::query()
            ->where('reference_type', 'wip_to_finished_promotion')
            ->where('reference_id', $order->product_id)
            ->where('created_at', '>=', $order->created_at)
            ->exists();
    }

    private function reverseFullWipPromotion(ConfirmedManfucture $order, int $userId, string $actor): void
    {
        $wipStock = ProductionWarehouseResolver::wipStock();
        $fgStock = ProductionWarehouseResolver::finishedGoodsStock();
        if (! $wipStock || ! $fgStock) {
            throw new \RuntimeException('تعذر عكس ترقية WIP — مخازن غير مهيأة.');
        }

        $meta = is_array($order->completion_meta) ? $order->completion_meta : [];
        $categoryId = (int) ($meta['result_category_id'] ?? $order->product_id);
        $cat = Category::query()->lockForUpdate()->findOrFail($categoryId);

        $qty = (float) $order->quantity;
        if ($qty <= 0.0000001) {
            return;
        }

        $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($categoryId);
        $totalCost = round($qty * $avgCost, 4);

        $this->applyOutboundAllowNegative(
            $cat,
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إخراج من منتج تام',
            $actor,
        );

        $cat->refresh();
        $cat->warehouse = $wipStock->name;
        $cat->stock_id = $wipStock->id;
        $cat->product_type = ProductType::SemiFinished->value;
        $cat->allow_wip_sale = true;
        $cat->save();

        $this->ledger->recordInbound(
            $cat->fresh(),
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            true,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إرجاع إلى تحت التشغيل',
            null,
            $actor,
        );

        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($categoryId);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function reversePartialNewWip(ConfirmedManfucture $order, array $meta, int $userId, string $actor): void
    {
        $sourceId = (int) $order->product_id;
        $targetId = (int) ($meta['new_category_id'] ?? $meta['result_category_id'] ?? 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('تعذر عكس أمر التصنيع: بيانات WIP (صنف جديد) غير مكتملة.');
        }

        $qty = (float) $order->quantity;
        $target = Category::query()->lockForUpdate()->findOrFail($targetId);

        $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($targetId);
        $totalCost = round($qty * $avgCost, 4);

        $this->applyOutboundAllowNegative(
            $target,
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إزالة من صنف تام جديد',
            $actor,
        );

        $source = Category::query()->lockForUpdate()->findOrFail($sourceId);
        $this->ledger->recordInbound(
            $source->fresh(),
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            true,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إرجاع إلى تحت التشغيل',
            null,
            $actor,
        );

        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($sourceId);
        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($targetId);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function reversePartialMergeWip(ConfirmedManfucture $order, array $meta, int $userId, string $actor): void
    {
        $sourceId = (int) $order->product_id;
        $targetId = (int) ($meta['result_category_id'] ?? 0);
        if ($targetId <= 0) {
            throw new \RuntimeException('تعذر عكس أمر التصنيع: بيانات دمج WIP غير مكتملة.');
        }

        $qty = (float) $order->quantity;
        $target = Category::query()->lockForUpdate()->findOrFail($targetId);

        $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($sourceId);
        $totalCost = round($qty * $avgCost, 4);

        $this->applyOutboundAllowNegative(
            $target,
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إزالة من صنف الدمج',
            $actor,
        );

        $source = Category::query()->lockForUpdate()->findOrFail($sourceId);
        $this->ledger->recordInbound(
            $source->fresh(),
            InventoryMovementType::Transfer,
            $qty,
            $avgCost,
            $totalCost,
            true,
            'manufacture_order_reversal',
            $order->id,
            'عكس أمر تصنيع #' . $order->id . ' — إرجاع إلى تحت التشغيل',
            null,
            $actor,
        );

        $target->refresh();
        $target->sell_total_price = bcmul((string) ($target->quantity ?? '0'), (string) ($target->category_price ?? '0'), 4);
        $target->save();

        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($sourceId);
        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($targetId);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, InventoryMovement>  $movements
     */
    private function reverseTransferMovements($movements, int $orderId, string $actor): void
    {
        foreach ($movements as $mov) {
            $cat = Category::query()->lockForUpdate()->findOrFail((int) $mov->category_id);
            $qty = (float) $mov->quantity;
            $uc = (float) $mov->unit_cost;
            $tc = (float) $mov->total_cost;

            if ($mov->direction === 'out') {
                $this->ledger->recordInbound(
                    $cat,
                    InventoryMovementType::Transfer,
                    $qty,
                    $uc,
                    $tc,
                    true,
                    'manufacture_order_reversal',
                    $orderId,
                    'عكس أمر تصنيع #' . $orderId,
                    null,
                    $actor,
                );
            } else {
                $this->applyOutboundAllowNegative(
                    $cat,
                    InventoryMovementType::Transfer,
                    $qty,
                    $uc,
                    $tc,
                    'manufacture_order_reversal',
                    $orderId,
                    'عكس أمر تصنيع #' . $orderId,
                    $actor,
                );
            }
        }
    }

    /**
     * عكس أمر تصنيع: يُسمح بخصم الكمية حتى لو أصبح الرصيد سالباً.
     */
    private function applyOutboundAllowNegative(
        Category $category,
        InventoryMovementType $type,
        float $qty,
        float $unitCost,
        float $totalCost,
        string $referenceType,
        int $referenceId,
        string $reason,
        string $actor,
    ): void {
        if ($qty <= 0.0000001) {
            return;
        }

        $cat = Category::query()->lockForUpdate()->findOrFail($category->id);
        $cat->quantity = (float) $cat->quantity - $qty;

        if (abs($totalCost) > 0.0000001) {
            $cat->total_price = round((float) ($cat->total_price ?? 0) - $totalCost, 4);
        }

        $cat->save();
        CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $cat->id);

        $this->ledger->appendOutboundMovement(
            $cat->fresh(),
            $type,
            $qty,
            $unitCost,
            $totalCost,
            $referenceType,
            $referenceId,
            $reason,
            null,
            $actor,
        );
    }

    private function reverseGlEntries(ConfirmedManfucture $order, int $userId): void
    {
        $orderId = (int) $order->id;
        $patterns = [
            '%أمر تصنيع #' . $orderId . '%',
            '%أمر #' . $orderId . '%',
            '%تأكيد تصنيع #' . $orderId . '%',
        ];

        $entryIds = DailyEntry::query()
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $pattern) {
                    $q->orWhere('description', 'like', $pattern);
                }
            })
            ->pluck('id')
            ->all();

        $meta = is_array($order->completion_meta) ? $order->completion_meta : [];
        if (! empty($meta['daily_entry_id'])) {
            $entryIds[] = (int) $meta['daily_entry_id'];
        }

        $entryIds = array_values(array_unique(array_filter($entryIds)));

        foreach (DailyEntry::query()->whereIn('id', $entryIds)->get() as $original) {
            $this->reverseSingleDailyEntry($original, $orderId, $userId);
        }
    }

    private function reverseSingleDailyEntry(DailyEntry $original, int $orderId, int $userId): void
    {
        $items = DailyEntryItem::query()->where('daily_entry_id', $original->id)->get();
        if ($items->isEmpty()) {
            return;
        }

        $reversal = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'عكس — ' . $original->description,
            'user_id' => $userId,
        ]);

        $affected = [];
        foreach ($items as $line) {
            DailyEntryItem::create([
                'daily_entry_id' => $reversal->id,
                'account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'notes' => 'عكس أمر تصنيع #' . $orderId,
            ]);

            AccountEntry::create([
                'tree_account_id' => $line->account_id,
                'debit' => (float) $line->credit,
                'credit' => (float) $line->debit,
                'description' => $reversal->description,
                'daily_entry_id' => $reversal->id,
            ]);

            $affected[(int) $line->account_id] = true;
        }

        foreach (array_keys($affected) as $accountId) {
            $this->accountingService->updateAccountHierarchyBalances($accountId);
        }
    }

    private function appendAuditReversalMovements(int $orderId, string $actor): void
    {
        $refTypes = array_merge([
            'confirmed_manufacture',
            'manufacture_confirm_complete',
            'manufacture_confirm_wip_only',
            'manufacture_done',
        ], self::WIP_TRANSFER_REF_TYPES);

        $movements = InventoryMovement::query()
            ->where('reference_id', $orderId)
            ->whereIn('reference_type', $refTypes)
            ->where('reference_type', '!=', 'manufacture_order_reversal')
            ->orderBy('id')
            ->get();

        foreach ($movements as $mov) {
            $cat = Category::query()->find((int) $mov->category_id);
            if (! $cat) {
                continue;
            }

            if (in_array($mov->reference_type, self::WIP_TRANSFER_REF_TYPES, true)) {
                continue;
            }

            $qty = (float) $mov->quantity;
            $uc = (float) $mov->unit_cost;
            $tc = (float) $mov->total_cost;
            $type = InventoryMovementType::tryFrom((string) $mov->movement_type)
                ?? InventoryMovementType::ManualAdjustment;

            if ($mov->direction === 'out') {
                $this->ledger->appendInboundMovement(
                    $cat,
                    $type,
                    $qty,
                    $uc,
                    $tc,
                    'manufacture_order_reversal',
                    $orderId,
                    'عكس حركة — أمر تصنيع #' . $orderId,
                    null,
                    $actor,
                );
            } else {
                $this->ledger->appendOutboundMovement(
                    $cat,
                    $type,
                    $qty,
                    $uc,
                    $tc,
                    'manufacture_order_reversal',
                    $orderId,
                    'عكس حركة — أمر تصنيع #' . $orderId,
                    null,
                    $actor,
                );
            }
        }
    }
}
