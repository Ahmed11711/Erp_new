<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\TreeAccount;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\AccountEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeePaymentAccountingService
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    public function getSalaryExpenseAccountId(): ?int
    {
        $id = \App\Models\Setting::where('key', 'salary_expense_account_id')->value('value');
        if ($id && TreeAccount::find($id)) {
            return (int) $id;
        }
        // يقتصر على مصروفات؛ يفضّل أعمق حساب (ورقة) لتفادي مطابقة أسماء خارج مجموعة المصروفات.
        $account = TreeAccount::query()
            ->where('type', 'expense')
            ->where(function ($q) {
                $q->where('name', 'like', '%رواتب وأجور%')
                    ->orWhere('name', 'like', '%رواتب موظفين%');
            })
            ->orderByDesc('level')
            ->orderByDesc('id')
            ->first();

        return $account ? $account->id : null;
    }

    public function postPayment(string $description, float $amount, int $bankId, ?string $date = null): bool
    {
        $bank = Bank::find($bankId);
        if (!$bank || !$bank->asset_id) {
            Log::warning('EmployeePayment: bank missing or no asset_id', ['bank_id' => $bankId]);

            return false;
        }

        return $this->postPaymentToCreditAccount($description, $amount, (int) $bank->asset_id, 'صرف من البنك', $date);
    }

    /**
     * قيد يومية: مدين مصروف رواتب، دائن حساب المصدر النقدي (بنك/خزينة/حساب خدمي مرتبط بشجرة الحسابات).
     */
    public function postPaymentToCreditAccount(string $description, float $amount, int $creditTreeAccountId, string $creditSideNotes = 'صرف', ?string $date = null): bool
    {
        $expenseAccountId = $this->getSalaryExpenseAccountId();
        if (!$expenseAccountId) {
            Log::warning('EmployeePayment: salary expense account not found');

            return false;
        }

        if (!TreeAccount::find($creditTreeAccountId)) {
            Log::warning('EmployeePayment: credit tree account missing', ['account_id' => $creditTreeAccountId]);

            return false;
        }

        $date = $date ?: now();

        DB::beginTransaction();
        try {
            $entryNumber = DailyEntry::getNextEntryNumber();

            $dailyEntry = DailyEntry::create([
                'date' => $date,
                'entry_number' => $entryNumber,
                'description' => $description,
                'user_id' => auth()->id(),
            ]);

            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $expenseAccountId,
                'debit' => $amount,
                'credit' => 0,
                'notes' => 'مصروف رواتب/سلف',
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $creditTreeAccountId,
                'debit' => 0,
                'credit' => $amount,
                'notes' => $creditSideNotes,
            ]);

            AccountEntry::create([
                'tree_account_id' => $expenseAccountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => $description,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            AccountEntry::create([
                'tree_account_id' => $creditTreeAccountId,
                'debit' => 0,
                'credit' => $amount,
                'description' => $description,
                'daily_entry_id' => $dailyEntry->id,
            ]);

            $this->accountingService->updateAccountHierarchyBalances($expenseAccountId);
            $this->accountingService->updateAccountHierarchyBalances($creditTreeAccountId);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmployeePayment posting failed: '.$e->getMessage());

            return false;
        }
    }
}
