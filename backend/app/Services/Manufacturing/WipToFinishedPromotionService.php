<?php

namespace App\Services\Manufacturing;

use App\Enums\InventoryMovementType;
use App\Enums\ProductType;
use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Item;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Items\ItemCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Promotes a semi-finished (WIP) item to finished goods:
 *  1. Outbound from WIP warehouse (quantity + valuation)
 *  2. Inbound to Finished Goods warehouse (quantity + valuation)
 *  3. Updates category row (warehouse, stock_id, product_type)
 *  4. Posts GL entry: Dr Finished Goods / Cr WIP
 */
final class WipToFinishedPromotionService
{
    public function __construct(
        private InventoryMovementLedgerService $ledger,
        private AccountingService $accountingService,
    ) {
    }

    /**
     * @return array{category: Category, daily_entry_id: int|null}
     */
    public function promote(int $categoryId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($categoryId, $userId) {
            $cat = Category::query()->lockForUpdate()->findOrFail($categoryId);
            $item = Item::query()->findOrFail($categoryId);

            $currentType = $item->resolvedProductType();
            if ($currentType !== ProductType::SemiFinished) {
                throw new \RuntimeException(
                    'هذا الصنف ليس تحت التشغيل (WIP). لا يمكن ترقيته إلا إذا كان نوعه semi_finished.'
                );
            }

            $qty = (float) ($cat->quantity ?? 0);
            if ($qty < 0.0000001) {
                throw new \RuntimeException('لا توجد كمية لهذا الصنف. أضف رصيداً أولاً قبل الترقية.');
            }

            $wipStock = ProductionWarehouseResolver::wipStock();
            $fgStock = ProductionWarehouseResolver::finishedGoodsStock();

            if (! $wipStock || ! $fgStock) {
                throw new \RuntimeException('تعذر العثور على مخازن تحت التشغيل أو المنتج التام. تأكد من إعداد المخازن.');
            }

            $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($categoryId);
            $totalCost = round($qty * $avgCost, 4);

            $actor = $userId !== null
                ? (\App\Models\User::find($userId)?->name ?? 'النظام')
                : (auth()->check() ? auth()->user()->name : 'النظام');

            $this->ledger->recordOutbound(
                $cat,
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_promotion',
                $categoryId,
                'ترقية صنف — إخراج من تحت التشغيل',
                null,
                $actor,
            );

            $cat->refresh();

            $cat->warehouse = $fgStock->name;
            $cat->stock_id = $fgStock->id;
            $cat->product_type = ProductType::Finished->value;
            $cat->allow_wip_sale = false;
            $cat->save();

            $this->ledger->recordInbound(
                $cat->fresh(),
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_promotion',
                $categoryId,
                'ترقية صنف — استلام في مخزن منتج تام',
                null,
                $actor,
            );

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($categoryId);

            $dailyEntryId = $this->postPromotionGl($categoryId, $totalCost, $fgStock, $wipStock, $userId);

            return [
                'category' => $cat->fresh(),
                'daily_entry_id' => $dailyEntryId,
            ];
        });
    }

    private function postPromotionGl(int $categoryId, float $amount, Stock $fgStock, Stock $wipStock, ?int $userId): ?int
    {
        if ($amount <= 0.00001) {
            return null;
        }

        $fg = TreeAccount::resolveInventoryAccountForStock($fgStock);
        $wip = TreeAccount::resolveInventoryAccountForStock($wipStock);

        if (! $fg || ! $wip) {
            Log::warning('WipToFinishedPromotionService: skip GL — missing FG or WIP account', [
                'category_id' => $categoryId,
            ]);
            return null;
        }

        if ((int) $fg->id === (int) $wip->id) {
            return null;
        }

        $daily = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'ترقية صنف من تحت التشغيل إلى منتج تام — صنف #' . $categoryId,
            'user_id' => $userId,
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $daily->id,
            'account_id' => $fg->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'إضافة منتج تام للمخزون (ترقية)',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $daily->id,
            'account_id' => $wip->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'إخراج من تحت التشغيل (ترقية)',
        ]);

        AccountEntry::create([
            'tree_account_id' => $fg->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $daily->description,
            'daily_entry_id' => $daily->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $wip->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $daily->description,
            'daily_entry_id' => $daily->id,
        ]);

        $this->accountingService->updateAccountHierarchyBalances($fg->id);
        $this->accountingService->updateAccountHierarchyBalances($wip->id);

        return (int) $daily->id;
    }

    /**
     * تأكيد تصنيع من شاشة "تأكيد أمر التصنيع" لصنف في مخزن تحت التشغيل:
     * - auto: إن كانت الكمية = كل الرصيد → ترقية كاملة؛ وإلا → صنف جديد في تام بنفس الوصفة.
     * - full_same: ترقية كاملة (يجب أن تساوي الكمية كل الرصيد).
     * - partial_new: إنشاء صنف جديد في مخزن تام ونقل الكمية مع الاحتفاظ بـ recipe_id.
     * - partial_merge: دمج الكمية في صنف تام موجود (يفضّل نفس recipe_id).
     *
     * @return array{strategy: string, source_category: Category, result_category: Category, new_category: ?Category, daily_entry_id: int|null}
     */
    public function transferForManufactureConfirm(
        int $sourceCategoryId,
        float $qty,
        string $mode,
        ?int $mergeTargetCategoryId,
        ?int $confirmedManufactureId,
        ?int $userId,
    ): array {
        $mode = strtolower(trim($mode));
        $allowed = ['auto', 'full_same', 'partial_new', 'partial_merge'];
        if (! in_array($mode, $allowed, true)) {
            throw new \InvalidArgumentException('قيمة غير صالحة لـ wip_mode.');
        }

        $wipStock = ProductionWarehouseResolver::wipStock();
        $fgStock = ProductionWarehouseResolver::finishedGoodsStock();
        if (! $wipStock || ! $fgStock) {
            throw new \RuntimeException('تعذر العثور على مخازن تحت التشغيل أو المنتج التام. تأكد من إعداد المخازن.');
        }

        $sourceRow = Category::query()->findOrFail($sourceCategoryId);
        $sourceItem = Item::query()->findOrFail($sourceCategoryId);
        if ($sourceItem->resolvedProductType() !== ProductType::SemiFinished) {
            throw new \RuntimeException('هذا المسار مخصّص لأصناف تحت التشغيل (WIP) فقط.');
        }

        $avail = (float) ($sourceRow->quantity ?? 0);
        if ($qty <= 0.0000001) {
            throw new \RuntimeException('الكمية يجب أن تكون أكبر من صفر.');
        }
        if ($qty > $avail + 0.00001) {
            throw new \RuntimeException('الكمية المطلوبة ('.$qty.') تتجاوز الرصيد المتاح ('.$avail.').');
        }

        if ($mode === 'auto') {
            $mode = abs($avail - $qty) < 0.0001 ? 'full_same' : 'partial_new';
        }

        if ($mode === 'full_same') {
            if (abs($avail - $qty) > 0.0001) {
                throw new \RuntimeException(
                    'وضع «تحويل كامل» يتطلب أن تساوي الكمية المدخلة كل رصيد تحت التشغيل، أو اختر استراتيجية أخرى.'
                );
            }

            $r = $this->promote($sourceCategoryId, $userId);
            $cat = $r['category'];

            return [
                'strategy' => 'full_same',
                'source_category' => $cat,
                'result_category' => $cat,
                'new_category' => null,
                'daily_entry_id' => $r['daily_entry_id'],
            ];
        }

        if ($mode === 'partial_merge') {
            if ($mergeTargetCategoryId === null || $mergeTargetCategoryId <= 0) {
                throw new \RuntimeException('حدد صنف المنتج التام المستهدف لدمج الكمية (wip_target_product_id).');
            }
            if ($mergeTargetCategoryId === $sourceCategoryId) {
                throw new \RuntimeException('لا يمكن دمج الصنف مع نفسه.');
            }

            return $this->transferPartialMerge(
                $sourceCategoryId,
                $qty,
                $mergeTargetCategoryId,
                $wipStock,
                $fgStock,
                $confirmedManufactureId,
                $userId
            );
        }

        return $this->transferPartialNew(
            $sourceCategoryId,
            $qty,
            $wipStock,
            $fgStock,
            $confirmedManufactureId,
            $userId
        );
    }

    /**
     * @return array{strategy: string, source_category: Category, result_category: Category, new_category: ?Category, daily_entry_id: int|null}
     */
    private function transferPartialNew(
        int $sourceCategoryId,
        float $qty,
        Stock $wipStock,
        Stock $fgStock,
        ?int $confirmedManufactureId,
        ?int $userId,
    ): array {
        return DB::transaction(function () use ($sourceCategoryId, $qty, $wipStock, $fgStock, $confirmedManufactureId, $userId) {
            $source = Category::query()->lockForUpdate()->findOrFail($sourceCategoryId);
            $avail = (float) ($source->quantity ?? 0);
            if ($qty > $avail + 0.00001) {
                throw new \RuntimeException('رصيد غير كافٍ بعد القفل على الصف.');
            }

            $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($sourceCategoryId);
            $totalCost = round($qty * $avgCost, 4);

            $actor = $userId !== null
                ? (\App\Models\User::find($userId)?->name ?? 'النظام')
                : (auth()->check() ? auth()->user()->name : 'النظام');

            $refId = $confirmedManufactureId ?? $sourceCategoryId;
            $refLabel = $confirmedManufactureId
                ? 'تأكيد تصنيع #'.$confirmedManufactureId.' — صنف جديد تام'
                : 'ترقية جزئية — صنف جديد تام';

            $this->ledger->recordOutbound(
                $source,
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_split',
                $refId,
                'إخراج من تحت التشغيل — '.$refLabel,
                null,
                $actor,
            );

            $source->refresh();

            $fgName = $this->uniqueFinishedGoodsName((string) ($source->category_name ?? 'صنف'), $fgStock->name);
            $new = new Category;
            $new->category_name = $fgName;
            $new->category_price = $source->category_price;
            $new->unit_price = $source->unit_price;
            $new->quantity = 0;
            $new->total_price = 0;
            $new->sell_total_price = 0;
            $new->initial_balance = 0;
            $new->minimum_quantity = $source->minimum_quantity;
            $new->warehouse = $fgStock->name;
            $new->stock_id = $fgStock->id;
            $new->production_id = $source->production_id;
            $new->measurement_id = $source->measurement_id;
            $new->category_image = $source->category_image;
            $new->color = $source->color;
            $new->recipe_id = $source->recipe_id;
            $new->product_type = ProductType::Finished->value;
            $new->allow_wip_sale = false;
            if ($source->lineage_root_id) {
                $new->lineage_root_id = $source->lineage_root_id;
            } else {
                $new->lineage_root_id = $sourceCategoryId;
            }
            $new->item_code = null;
            $new->save();

            app(ItemCodeService::class)->ensureCode(Item::query()->findOrFail($new->id));

            $this->ledger->recordInbound(
                $new->fresh(),
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_split',
                $refId,
                'استلام في منتج تام — '.$refLabel,
                null,
                $actor,
            );

            $new->refresh();
            $sell = bcmul((string) ($new->quantity ?? '0'), (string) ($new->category_price ?? '0'), 4);
            $new->sell_total_price = $sell;
            $new->save();

            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $new->id);

            $dailyEntryId = $this->postPromotionGl($sourceCategoryId, $totalCost, $fgStock, $wipStock, $userId);

            return [
                'strategy' => 'partial_new',
                'source_category' => $source->fresh(),
                'result_category' => $new->fresh(),
                'new_category' => $new->fresh(),
                'daily_entry_id' => $dailyEntryId,
            ];
        });
    }

    /**
     * @return array{strategy: string, source_category: Category, result_category: Category, new_category: ?Category, daily_entry_id: int|null}
     */
    private function transferPartialMerge(
        int $sourceCategoryId,
        float $qty,
        int $targetCategoryId,
        Stock $wipStock,
        Stock $fgStock,
        ?int $confirmedManufactureId,
        ?int $userId,
    ): array {
        return DB::transaction(function () use ($sourceCategoryId, $qty, $targetCategoryId, $wipStock, $fgStock, $confirmedManufactureId, $userId) {
            $source = Category::query()->lockForUpdate()->findOrFail($sourceCategoryId);
            $target = Category::query()->lockForUpdate()->findOrFail($targetCategoryId);
            $targetItem = Item::query()->findOrFail($targetCategoryId);

            if ($targetItem->resolvedProductType() !== ProductType::Finished) {
                throw new \RuntimeException('صنف الدمج يجب أن يكون منتجاً تاماً.');
            }
            $inFgWarehouse = ((int) $target->stock_id === (int) $fgStock->id)
                || trim((string) $target->warehouse) === trim((string) $fgStock->name);
            if (! $inFgWarehouse) {
                throw new \RuntimeException('صنف الدمج يجب أن يكون في مخزن المنتج التام.');
            }

            $srcRecipe = $source->recipe_id ? (int) $source->recipe_id : null;
            $tgtRecipe = $target->recipe_id ? (int) $target->recipe_id : null;
            if ($srcRecipe && $tgtRecipe && $srcRecipe !== $tgtRecipe) {
                throw new \RuntimeException('صنف التام المستهدف مرتبط بوصفة مختلفة عن صنف تحت التشغيل.');
            }

            $avail = (float) ($source->quantity ?? 0);
            if ($qty > $avail + 0.00001) {
                throw new \RuntimeException('رصيد غير كافٍ بعد القفل على الصف.');
            }

            $avgCost = CategoryInventoryCostService::averageCostForCategoryIssue($sourceCategoryId);
            $totalCost = round($qty * $avgCost, 4);

            $actor = $userId !== null
                ? (\App\Models\User::find($userId)?->name ?? 'النظام')
                : (auth()->check() ? auth()->user()->name : 'النظام');

            $refId = $confirmedManufactureId ?? $sourceCategoryId;
            $refLabel = $confirmedManufactureId
                ? 'تأكيد تصنيع #'.$confirmedManufactureId.' — دمج في تام #'.$targetCategoryId
                : 'ترقية جزئية — دمج في تام #'.$targetCategoryId;

            $this->ledger->recordOutbound(
                $source,
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_merge',
                $refId,
                'إخراج من تحت التشغيل — '.$refLabel,
                null,
                $actor,
            );

            $this->ledger->recordInbound(
                $target->fresh(),
                InventoryMovementType::Transfer,
                $qty,
                $avgCost,
                $totalCost,
                true,
                'wip_to_finished_merge',
                $refId,
                'استلام من تحت التشغيل — '.$refLabel,
                null,
                $actor,
            );

            $target->refresh();
            $target->sell_total_price = bcmul((string) ($target->quantity ?? '0'), (string) ($target->category_price ?? '0'), 4);
            $target->save();
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage((int) $target->id);

            $dailyEntryId = $this->postPromotionGl($sourceCategoryId, $totalCost, $fgStock, $wipStock, $userId);

            return [
                'strategy' => 'partial_merge',
                'source_category' => $source->fresh(),
                'result_category' => $target->fresh(),
                'new_category' => null,
                'daily_entry_id' => $dailyEntryId,
            ];
        });
    }

    private function uniqueFinishedGoodsName(string $baseName, string $finishedWarehouseName): string
    {
        $candidates = [
            $baseName.' (تام)',
            $baseName.' — منتج تام',
        ];
        for ($i = 2; $i <= 50; $i++) {
            $candidates[] = $baseName.' (تام '.$i.')';
        }
        foreach ($candidates as $name) {
            $exists = Category::query()
                ->where('warehouse', $finishedWarehouseName)
                ->whereRaw('TRIM(category_name) = ?', [trim($name)])
                ->exists();
            if (! $exists) {
                return $name;
            }
        }

        throw new \RuntimeException('تعذر توليد اسم فريد للصنف في مخزن المنتج التام.');
    }
}
