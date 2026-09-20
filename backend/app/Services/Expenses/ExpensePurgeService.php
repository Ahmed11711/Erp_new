<?php

namespace App\Services\Expenses;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Expense;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حذف جميع المصروفات مع قيودها المحاسبية وإعادة أرصدة الشجرة ومصادر الدفع.
 */
class ExpensePurgeService
{
    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly TreeAccountBalanceRebuildService $balanceRebuild,
    ) {}

    /**
     * @return array{
     *     active_expense_count: int,
     *     total_expense_count: int,
     *     gl_entry_count: int,
     *     expense_line_count: int
     * }
     */
    public function preview(): array
    {
        return [
            'active_expense_count' => $this->activeExpenseQuery()->count(),
            'total_expense_count' => (int) Expense::query()->count(),
            'gl_entry_count' => (int) AccountEntry::query()
                ->where('entry_batch_code', 'LIKE', 'EXP-%')
                ->count(),
            'expense_line_count' => Schema::hasTable('expense_lines')
                ? (int) DB::table('expense_lines')->count()
                : 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function purge(): array
    {
        $this->counts = [];

        DB::transaction(function () {
            $activeExpenses = $this->activeExpenseQuery()->get();
            foreach ($activeExpenses as $expense) {
                $this->restorePaymentSource($expense);
            }

            $expenseNumbers = Expense::query()
                ->pluck('expense_number')
                ->filter(fn ($n) => $n !== null && $n !== '')
                ->unique()
                ->values()
                ->all();

            $this->counts['قيود محاسبية (مصروفات)'] = $this->deleteExpenseGlEntries($expenseNumbers);

            if ($expenseNumbers !== []) {
                $this->counts['حركات بنك (مصروفات)'] = (int) DB::table('bank_details')
                    ->whereIn('ref', $expenseNumbers)
                    ->where('type', 'المصروفات')
                    ->delete();
            }

            if (Schema::hasTable('expense_lines')) {
                $this->counts['بنود مصروفات'] = (int) DB::table('expense_lines')->delete();
            }

            $this->counts['مصروفات'] = (int) Expense::query()->delete();
        });

        $rebuild = $this->balanceRebuild->rebuildAll();
        $this->counts['حسابات شجرية (أوراق)'] = $rebuild['leaves_updated'];
        $this->counts['حسابات شجرية (آباء)'] = $rebuild['parents_updated'];

        return $this->counts;
    }

    private function activeExpenseQuery()
    {
        return Expense::query()
            ->where(function ($q) {
                $q->where('status', '!=', '1')->orWhereNull('status');
            })
            ->where('amount', '>', 0);
    }

    private function restorePaymentSource(Expense $expense): void
    {
        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            return;
        }

        $paymentType = $expense->payment_type
            ?? ($expense->safe_id ? 'safe' : ($expense->service_account_id ? 'service_account' : 'bank'));

        if ($paymentType === 'safe' && $expense->safe_id) {
            Safe::whereKey($expense->safe_id)->increment('balance', $amount);

            return;
        }

        if ($paymentType === 'service_account' && $expense->service_account_id) {
            ServiceAccount::whereKey($expense->service_account_id)->increment('balance', $amount);

            return;
        }

        if ($expense->bank_id) {
            Bank::whereKey($expense->bank_id)->increment('balance', $amount);
        }
    }

    /**
     * @param  list<string>  $expenseNumbers
     */
    private function deleteExpenseGlEntries(array $expenseNumbers): int
    {
        return (int) AccountEntry::query()
            ->where(function ($q) use ($expenseNumbers) {
                $q->where('entry_batch_code', 'LIKE', 'EXP-%');
                foreach ($expenseNumbers as $num) {
                    $q->orWhere('description', 'LIKE', '%' . $num . '%');
                }
            })
            ->delete();
    }
}
