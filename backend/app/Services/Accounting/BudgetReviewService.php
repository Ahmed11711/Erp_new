<?php

namespace App\Services\Accounting;

use App\Models\TreeAccount;
use App\Models\AccountEntry;
use Carbon\Carbon;

/**
 * Budget review: enforces that accounts with budget_amount do not exceed
 * their budget when posting entries (daily entries, vouchers, etc.).
 */
class BudgetReviewService
{
    /**
     * Check if posting the given items would exceed any account budget.
     * Items: array of ['tree_account_id' => id, 'debit' => x, 'credit' => y]
     * For expense/asset accounts we check debit total; for revenue/liability we may check credit.
     *
     * @param array $items
     * @param string|null $date For period-based budget (yearly/monthly)
     * @return array ['valid' => bool, 'message' => string, 'violations' => []]
     */
    public function checkBudget(array $items, ?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : now();
        $violations = [];

        $byAccount = [];
        foreach ($items as $item) {
            $id = $item['tree_account_id'] ?? $item['account_id'] ?? null;
            if (!$id) continue;
            if (!isset($byAccount[$id])) {
                $byAccount[$id] = ['debit' => 0, 'credit' => 0];
            }
            $byAccount[$id]['debit'] += (float)($item['debit'] ?? 0);
            $byAccount[$id]['credit'] += (float)($item['credit'] ?? 0);
        }

        foreach ($byAccount as $accountId => $totals) {
            $account = TreeAccount::find($accountId);
            if (!$account || $account->budget_amount === null) continue;

            $existingDebit = $this->getExistingDebitForBudget($accountId, $account->budget_period, $date);
            $existingCredit = $this->getExistingCreditForBudget($accountId, $account->budget_period, $date);

            // Expense/Asset: budget typically limits debit (spending)
            if (in_array($account->type, ['expense', 'asset'])) {
                $newDebit = $existingDebit + $totals['debit'] - $totals['credit'];
                if ($newDebit > (float)$account->budget_amount) {
                    $violations[] = [
                        'account_id' => $accountId,
                        'account_name' => $account->name,
                        'budget_amount' => $account->budget_amount,
                        'current_plus_new' => $newDebit,
                    ];
                }
            }

            // Revenue: budget could limit credit (income cap) - optional
            if (in_array($account->type, ['revenue']) && $account->budget_amount !== null) {
                $newCredit = $existingCredit + $totals['credit'] - $totals['debit'];
                if ($newCredit > (float)$account->budget_amount) {
                    $violations[] = [
                        'account_id' => $accountId,
                        'account_name' => $account->name,
                        'budget_amount' => $account->budget_amount,
                        'current_plus_new' => $newCredit,
                    ];
                }
            }
        }

        if (!empty($violations)) {
            $names = collect($violations)->pluck('account_name')->implode(', ');
            return [
                'valid' => false,
                'message' => 'تجاوز الميزانية المعتمدة للحسابات: ' . $names,
                'violations' => $violations,
            ];
        }

        return ['valid' => true, 'message' => '', 'violations' => []];
    }

    private function getExistingDebitForBudget(int $treeAccountId, ?string $period, Carbon $date): float
    {
        $query = AccountEntry::where('tree_account_id', $treeAccountId);
        $this->applyPeriod($query, $period, $date, 'debit');
        return (float)$query->sum('debit');
    }

    private function getExistingCreditForBudget(int $treeAccountId, ?string $period, Carbon $date): float
    {
        $query = AccountEntry::where('tree_account_id', $treeAccountId);
        $this->applyPeriod($query, $period, $date, 'credit');
        return (float)$query->sum('credit');
    }

    private function applyPeriod($query, ?string $period, Carbon $date, string $column): void
    {
        if ($period === 'monthly') {
            $query->whereYear('created_at', $date->year)->whereMonth('created_at', $date->month);
        } elseif ($period === 'yearly') {
            $query->whereYear('created_at', $date->year);
        }
    }
}
