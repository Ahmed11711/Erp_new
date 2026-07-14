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

        return $this->postPaymentToCreditAccount($description, $amount, (int) $bank->asset_id, 'صرف من البنك', $date) !== null;
    }

    /**
     * استحقاق راتب: مدين مصروف رواتب، دائن مستحقات الموظف.
     *
     * @return int|null daily_entry_id
     */
    public function postSalaryAccrual(string $description, float $amount, int $employeePayableAccountId, ?string $date = null): ?int
    {
        return $this->postTwoLegEntry(
            $description,
            $amount,
            $this->getSalaryExpenseAccountId(),
            $employeePayableAccountId,
            'مصروف رواتب — استحقاق',
            'مستحقات راتب',
            $date
        );
    }

    /**
     * تعديل استحقاق: increase=true => مدين مصروف دائن مستحقات، وإلا العكس.
     *
     * @return int|null daily_entry_id
     */
    public function postAccrualAdjustment(
        string $description,
        float $amount,
        int $employeePayableAccountId,
        bool $increase,
        ?string $date = null
    ): ?int {
        if ($increase) {
            return $this->postTwoLegEntry(
                $description,
                $amount,
                $this->getSalaryExpenseAccountId(),
                $employeePayableAccountId,
                'تعديل استحقاق — زيادة',
                'مستحقات راتب',
                $date
            );
        }

        return $this->postTwoLegEntry(
            $description,
            $amount,
            $employeePayableAccountId,
            $this->getSalaryExpenseAccountId(),
            'تعديل استحقاق — نقص',
            'مصروف رواتب',
            $date
        );
    }

    /**
     * صرف راتب: مدين مستحقات الموظف، دائن المصدر النقدي.
     *
     * @return int|null daily_entry_id
     */
    public function postSalaryDisbursement(
        string $description,
        float $amount,
        int $employeePayableAccountId,
        int $creditTreeAccountId,
        string $creditSideNotes = 'صرف',
        ?string $date = null
    ): ?int {
        return $this->postTwoLegEntry(
            $description,
            $amount,
            $employeePayableAccountId,
            $creditTreeAccountId,
            'سداد مستحقات راتب',
            $creditSideNotes,
            $date
        );
    }

    /**
     * قيد يومية قديم: مدين مصروف رواتب، دائن حساب المصدر النقدي (سلف / ترحيل بدون ربط موظف).
     *
     * @return int|null daily_entry_id
     */
    public function postPaymentToCreditAccount(string $description, float $amount, int $creditTreeAccountId, string $creditSideNotes = 'صرف', ?string $date = null): ?int
    {
        return $this->postTwoLegEntry(
            $description,
            $amount,
            $this->getSalaryExpenseAccountId(),
            $creditTreeAccountId,
            'مصروف رواتب/سلف',
            $creditSideNotes,
            $date
        );
    }

    /**
     * @return int|null daily_entry_id
     */
    private function postTwoLegEntry(
        string $description,
        float $amount,
        ?int $debitAccountId,
        ?int $creditAccountId,
        string $debitNotes,
        string $creditNotes,
        ?string $date = null
    ): ?int {
        if (! $debitAccountId || ! TreeAccount::find($debitAccountId)) {
            Log::warning('EmployeePayment: debit tree account missing', ['account_id' => $debitAccountId]);

            return null;
        }

        if (! $creditAccountId || ! TreeAccount::find($creditAccountId)) {
            Log::warning('EmployeePayment: credit tree account missing', ['account_id' => $creditAccountId]);

            return null;
        }

        if ($amount <= 0) {
            return null;
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
                'account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'notes' => $debitNotes,
            ]);
            DailyEntryItem::create([
                'daily_entry_id' => $dailyEntry->id,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'notes' => $creditNotes,
            ]);

            AccountEntry::create([
                'tree_account_id' => $debitAccountId,
                'debit' => $amount,
                'credit' => 0,
                'description' => $description,
                'daily_entry_id' => $dailyEntry->id,
            ]);
            AccountEntry::create([
                'tree_account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $amount,
                'description' => $description,
                'daily_entry_id' => $dailyEntry->id,
            ]);

            $this->accountingService->updateAccountHierarchyBalances($debitAccountId);
            $this->accountingService->updateAccountHierarchyBalances($creditAccountId);

            DB::commit();

            return (int) $dailyEntry->id;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('EmployeePayment posting failed: '.$e->getMessage());

            return null;
        }
    }
}
