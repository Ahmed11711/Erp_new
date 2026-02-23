<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;

/**
 * Creates daily entry + account entries for employee payments (advance, salary)
 * Debit: salary expense account, Credit: bank (or safe/service when supported)
 */
class EmployeePaymentAccountingService
{
    public function getSalaryExpenseAccountId(): ?int
    {
        $id = \App\Models\Setting::where('key', 'salary_expense_account_id')->value('value');
        if ($id && TreeAccount::find($id)) {
            return (int) $id;
        }
        $account = TreeAccount::where('name', 'like', '%رواتب وأجور%')
            ->orWhere('name', 'like', '%رواتب موظفين%')
            ->orderBy('level', 'desc')
            ->first();
        return $account ? $account->id : null;
    }

    /**
     * Post accounting for employee payment: debit expense, credit bank.
     * Returns true if entry was created.
     */
    public function postPayment(string $description, float $amount, int $bankId, ?string $date = null): bool
    {
        $bank = Bank::find($bankId);
        if (!$bank || !$bank->asset_id) {
            return false;
        }
        $expenseAccountId = $this->getSalaryExpenseAccountId();
        if (!$expenseAccountId) {
            return false;
        }

        $date = $date ?: now();
        $lastEntry = DailyEntry::orderByDesc('entry_number')->first();
        $entryNumber = $lastEntry ? (int) $lastEntry->entry_number + 1 : 1;
        $dailyEntry = DailyEntry::create([
            'date' => $date,
            'entry_number' => str_pad($entryNumber, 6, '0', STR_PAD_LEFT),
            'description' => $description,
            'user_id' => auth()->id(),
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $expenseAccountId,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'زيادة (مصروف رواتب/سلف)',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $dailyEntry->id,
            'account_id' => $bank->asset_id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'نقصان (صرف من البنك)',
        ]);

        AccountEntry::create([
            'tree_account_id' => $expenseAccountId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $bank->asset_id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'daily_entry_id' => $dailyEntry->id,
        ]);

        $expenseAcc = TreeAccount::find($expenseAccountId);
        $expenseAcc->increment('balance', $amount);
        $expenseAcc->increment('debit_balance', $amount);
        $bankAcc = TreeAccount::find($bank->asset_id);
        $bankAcc->decrement('balance', $amount);
        $bankAcc->increment('credit_balance', $amount);

        return true;
    }
}
