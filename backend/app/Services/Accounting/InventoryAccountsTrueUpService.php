<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

/**
 * مطابقة حسابات المخزون الفرعية في الشجرة مع مجموع تكلفة الأصناف (categories.total_price) عبر قيد يومية واحد.
 */
class InventoryAccountsTrueUpService
{
    public function __construct(
        private InventoryGlPostingService $inventoryGlPostingService,
    ) {
    }

    /**
     * رصيد الحساب الطرفي من القيود المحوسبة فقط (مدين − دائن).
     */
    public function bookBalanceFromEntries(int $treeAccountId): float
    {
        $d = (float) AccountEntry::query()->where('tree_account_id', $treeAccountId)->sum('debit');
        $c = (float) AccountEntry::query()->where('tree_account_id', $treeAccountId)->sum('credit');

        return round($d - $c, 2);
    }

    /**
     * أهداف التكلفة: مجموع categories.total_price حسب حساب المخزون المرتبط بكل صنف.
     *
     * @return array<int, float>
     */
    public function computeTargetCostByInventoryAccount(): array
    {
        $targets = [];

        $query = Category::query()
            ->whereNotNull('warehouse')
            ->where('warehouse', '!=', '')
            ->whereNotIn('warehouse', ['مخزن صيانة']);

        foreach ($query->cursor() as $cat) {
            $acc = TreeAccount::resolveInventoryAccountForCategoryRow($cat);
            if (! $acc) {
                continue;
            }
            $id = (int) $acc->id;
            $targets[$id] = ($targets[$id] ?? 0) + (float) ($cat->total_price ?? 0);
        }

        foreach ($targets as $k => $v) {
            $targets[$k] = round($v, 2);
        }

        return $targets;
    }

    /**
     * حسابات مخزون طرفية قياسية (مواد خام / تحت التشغيل / منتج تام / مخزون عام).
     *
     * @return array<int, int>
     */
    public function standardInventoryLeafAccountIds(): array
    {
        return TreeAccount::query()
            ->whereIn('detail_type', ['inventory_raw', 'inventory_wip', 'inventory_finished', 'inventory'])
            ->whereDoesntHave('children')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{adjustments: array<int, array<string, mixed>>, deltas_by_account: array<int, float>}
     */
    public function buildAdjustments(): array
    {
        $targets = $this->computeTargetCostByInventoryAccount();
        $idsFromCategories = array_keys($targets);
        $standardIds = $this->standardInventoryLeafAccountIds();
        $allIds = array_unique(array_merge($standardIds, $idsFromCategories));

        $adjustments = [];
        $deltasByAccount = [];

        foreach ($allIds as $accountId) {
            $acc = TreeAccount::query()->find($accountId);
            if (! $acc || $acc->type !== 'asset') {
                continue;
            }

            $target = round((float) ($targets[$accountId] ?? 0), 2);
            $book = $this->bookBalanceFromEntries($accountId);
            $delta = round($target - $book, 2);

            if (abs($delta) < 0.02) {
                continue;
            }

            $deltasByAccount[$accountId] = $delta;
            $adjustments[] = [
                'account_id' => $accountId,
                'account_code' => $acc->code,
                'account_name' => $acc->name,
                'target_cost_from_items' => $target,
                'book_balance_from_entries' => $book,
                'adjustment' => $delta,
            ];
        }

        return [
            'adjustments' => $adjustments,
            'deltas_by_account' => $deltasByAccount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $built = $this->buildAdjustments();

        return [
            'success' => true,
            'adjustments' => $built['adjustments'],
            'will_post' => count($built['deltas_by_account']) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?int $userId): array
    {
        $built = $this->buildAdjustments();
        $deltas = $built['deltas_by_account'];

        if (count($deltas) === 0) {
            return [
                'success' => true,
                'posted' => false,
                'message' => 'لا توجد فروقات بين تكلفة الأصناف وأرصدة الحسابات تتطلب قيداً.',
                'adjustments' => [],
            ];
        }

        return DB::transaction(function () use ($deltas, $built, $userId) {
            $journal = $this->inventoryGlPostingService->postInventoryAccountsTrueUpJournal($deltas, $userId);

            return [
                'success' => true,
                'posted' => true,
                'message' => 'تم إنشاء قيد التسوية وتحديث أرصدة الشجرة.',
                'adjustments' => $built['adjustments'],
                'daily_entry_id' => $journal['daily_entry_id'],
                'entry_number' => $journal['entry_number'],
                'journal_lines' => $journal['lines'],
            ];
        });
    }
}
