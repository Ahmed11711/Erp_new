<?php

namespace App\Services\Shipping;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Models\ShippingLineStatement;
use App\Models\TreeAccount;
use App\Models\shippingCompanyDetails;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShippingCompanyDeletionService
{
    public function __construct(
        private readonly TreeAccountBalanceRebuildService $balanceRebuild,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public function delete(ShippingCompany $company): void
    {
        $blockReason = $this->getBlockReason($company);
        if ($blockReason !== null) {
            throw new \InvalidArgumentException($blockReason);
        }

        DB::transaction(function () use ($company) {
            $locked = ShippingCompany::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            $blockReason = $this->getBlockReason($locked);
            if ($blockReason !== null) {
                throw new \InvalidArgumentException($blockReason);
            }

            $receivableTreeAccountId = $locked->receivable_tree_account_id
                ? (int) $locked->receivable_tree_account_id
                : null;
            $payableTreeAccountId = $locked->tree_account_id
                ? (int) $locked->tree_account_id
                : null;

            $parentIds = [];

            shippingCompanyDetails::query()
                ->where('shipping_company_id', $locked->id)
                ->delete();

            if (Schema::hasTable('shipping_line_statements')) {
                ShippingLineStatement::query()
                    ->where('shipping_company_id', $locked->id)
                    ->delete();
            }

            $locked->delete();

            foreach ([$receivableTreeAccountId, $payableTreeAccountId] as $treeAccountId) {
                if (! $treeAccountId) {
                    continue;
                }

                $parentId = (int) TreeAccount::query()->whereKey($treeAccountId)->value('parent_id');
                if ($parentId) {
                    $parentIds[] = $parentId;
                }

                $this->purgeTreeAccountLedger($treeAccountId);
                TreeAccount::query()->whereKey($treeAccountId)->forceDelete();
            }

            foreach (array_values(array_unique($parentIds)) as $parentId) {
                $this->balanceRebuild->rebuildAncestorChain($parentId);
            }
        });
    }

    public function getBlockReason(ShippingCompany $company): ?string
    {
        $label = $this->entityLabel($company);
        $name = trim((string) ($company->name ?? ''));

        $orderCount = OrderDetails::query()
            ->where(function ($q) use ($company) {
                $q->where('shipping_company_id', $company->id)
                    ->orWhere('collection_company_id', $company->id)
                    ->orWhere('shipping_provider_id', $company->id);
            })
            ->count();

        if ($orderCount > 0) {
            $suffix = $name !== '' ? ' «'.$name.'»' : '';

            return 'لا يمكن حذف '.$label.$suffix.' لارتباطه بـ '.$orderCount
                .' طلب. انقل الطلبات لمندوب/شركة أخرى أولاً.';
        }

        $detailsCount = shippingCompanyDetails::query()
            ->where('shipping_company_id', $company->id)
            ->count();

        if ($detailsCount > 0) {
            $suffix = $name !== '' ? ' «'.$name.'»' : '';

            return 'لا يمكن حذف '.$label.$suffix.' لوجود '.$detailsCount.' حركة في كشف الحساب.';
        }

        if (Schema::hasTable('shipping_line_statements')) {
            $statementCount = ShippingLineStatement::query()
                ->where('shipping_company_id', $company->id)
                ->count();

            if ($statementCount > 0) {
                $suffix = $name !== '' ? ' «'.$name.'»' : '';

                return 'لا يمكن حذف '.$label.$suffix.' لوجود '.$statementCount.' سجل في كشف خط الشحن.';
            }
        }

        $balance = round($company->displayBalance(), 2);
        if (abs($balance) > 0.000001) {
            $suffix = $name !== '' ? ' «'.$name.'»' : '';

            return 'لا يمكن حذف '.$label.$suffix.' لوجود رصيد '
                .number_format($balance, 2).'. يجب تسوية الرصيد أولاً.';
        }

        foreach (['receivable_tree_account_id', 'tree_account_id'] as $column) {
            $treeAccountId = $company->{$column} ? (int) $company->{$column} : null;
            if (! $treeAccountId) {
                continue;
            }

            $treeAccount = TreeAccount::query()->find($treeAccountId);
            if ($treeAccount && $treeAccount->children()->exists()) {
                return 'لا يمكن حذف '.$label.' لوجود حسابات فرعية مرتبطة به في شجرة الحسابات.';
            }
        }

        return null;
    }

    private function entityLabel(ShippingCompany $company): string
    {
        return ($company->type ?? '') === 'مندوب' ? 'المندوب' : 'شركة الشحن';
    }

    private function purgeTreeAccountLedger(int $treeAccountId): void
    {
        $dailyEntryIds = AccountEntry::query()
            ->where('tree_account_id', $treeAccountId)
            ->whereNotNull('daily_entry_id')
            ->pluck('daily_entry_id')
            ->all();

        AccountEntry::query()->where('tree_account_id', $treeAccountId)->delete();

        if (Schema::hasTable('daily_entry_items')) {
            DailyEntryItem::query()->where('account_id', $treeAccountId)->delete();
        }

        $dailyEntryIds = array_values(array_unique(array_map('intval', array_filter($dailyEntryIds))));
        if ($dailyEntryIds === []) {
            return;
        }

        AccountEntry::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        DailyEntryItem::query()->whereIn('daily_entry_id', $dailyEntryIds)->delete();
        DailyEntry::query()->whereIn('id', $dailyEntryIds)->delete();
    }
}
