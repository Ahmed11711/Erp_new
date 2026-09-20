<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\TreeAccount;

/**
 * إعادة بناء أرصدة شجرة الحسابات من account_entries مع تجميع صحيح للآباء.
 */
class TreeAccountBalanceRebuildService
{
    /**
     * @return array{leaves_updated:int, parents_updated:int}
     */
    public function rebuildAll(): array
    {
        $leavesUpdated = 0;
        $parentsUpdated = 0;

        TreeAccount::query()
            ->whereDoesntHave('children')
            ->each(function (TreeAccount $leaf) use (&$leavesUpdated) {
                $this->applyBalanceFromEntries($leaf);
                $leavesUpdated++;
            });

        $maxLevel = (int) TreeAccount::max('level');
        for ($level = $maxLevel - 1; $level >= 1; $level--) {
            TreeAccount::query()
                ->where('level', $level)
                ->whereHas('children')
                ->each(function (TreeAccount $parent) use (&$parentsUpdated) {
                    $this->applyBalanceFromChildren($parent);
                    $parentsUpdated++;
                });
        }

        return [
            'leaves_updated' => $leavesUpdated,
            'parents_updated' => $parentsUpdated,
        ];
    }

    /**
     * إعادة تجميع الأرصدة لسلسلة الأصول فقط (من حساب معيّن حتى الجذر).
     *
     * @return list<int> معرفات الحسابات المُحدَّثة
     */
    public function rebuildAncestorChain(int $accountId): array
    {
        $updated = [];
        $current = TreeAccount::query()->find($accountId);

        while ($current) {
            if ($current->children()->exists()) {
                $this->applyBalanceFromChildren($current);
            } else {
                $this->applyBalanceFromEntries($current);
            }
            $updated[] = $current->id;

            if (!$current->parent_id) {
                break;
            }
            $current = TreeAccount::query()->find($current->parent_id);
        }

        return $updated;
    }

    public function applyBalanceFromEntries(TreeAccount $account): void
    {
        $totals = AccountEntry::query()
            ->where('tree_account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
            ->first();

        $account->update([
            'debit_balance' => $totals->total_debit,
            'credit_balance' => $totals->total_credit,
            'balance' => $totals->total_debit - $totals->total_credit,
        ]);
    }

    public function applyBalanceFromChildren(TreeAccount $parent): void
    {
        $childSums = TreeAccount::query()
            ->where('parent_id', $parent->id)
            ->selectRaw('COALESCE(SUM(balance),0) as bal, COALESCE(SUM(debit_balance),0) as db, COALESCE(SUM(credit_balance),0) as cb')
            ->first();

        $ownEntries = AccountEntry::query()
            ->where('tree_account_id', $parent->id)
            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
            ->first();

        $debit = (float) $childSums->db + (float) $ownEntries->total_debit;
        $credit = (float) $childSums->cb + (float) $ownEntries->total_credit;

        $parent->update([
            'debit_balance' => $debit,
            'credit_balance' => $credit,
            'balance' => $debit - $credit,
        ]);
    }
}
