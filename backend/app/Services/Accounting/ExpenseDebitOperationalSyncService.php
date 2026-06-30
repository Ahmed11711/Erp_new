<?php

namespace App\Services\Accounting;

use App\Models\customerCompany;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * مزامنة الأرصدة التشغيلية للحسابات المدينة في المصروف (مورد، عميل، بنك، خزينة، …).
 */
class ExpenseDebitOperationalSyncService
{
    public function __construct(
        private readonly PaymentSourceOperationalLedgerService $paymentSourceSync,
    ) {}

    public function syncDebitLine(
        int $treeAccountId,
        float $amount,
        string $details,
        string $ref,
        ?string $date = null,
        bool $reverse = false
    ): void {
        if ($amount <= 0) {
            return;
        }

        $debit = $reverse ? 0.0 : $amount;
        $credit = $reverse ? $amount : 0.0;

        $this->paymentSourceSync->syncFromJournalLine(
            $treeAccountId,
            $debit,
            $credit,
            $details,
            $ref,
            $date
        );

        $this->syncSupplierIfLinked($treeAccountId, $amount, $ref, $reverse);
        $this->syncCustomerCompanyIfLinked($treeAccountId, $amount, $ref, $details, $date, $reverse);
    }

    private function syncSupplierIfLinked(
        int $treeAccountId,
        float $amount,
        string $ref,
        bool $reverse
    ): void {
        $supplier = Supplier::query()->where('tree_account_id', $treeAccountId)->first();
        if (! $supplier) {
            return;
        }

        $delta = $reverse ? $amount : -$amount;
        if (abs($delta) < 0.000001) {
            return;
        }

        $oldBalance = (float) $supplier->balance;
        $supplier->last_balance = $oldBalance;
        $supplier->balance = round($oldBalance + $delta, 2);
        $supplier->save();

        DB::table('supplier_balance')->insert([
            'balance_before' => $oldBalance,
            'balance_after' => $supplier->balance,
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    private function syncCustomerCompanyIfLinked(
        int $treeAccountId,
        float $amount,
        string $ref,
        string $details,
        ?string $date,
        bool $reverse
    ): void {
        $company = customerCompany::query()->where('tree_account_id', $treeAccountId)->first();
        if (! $company) {
            return;
        }

        if ($reverse) {
            app(CustomerCompanyDirectCashSyncService::class)->reverseByRef($ref);

            return;
        }

        if (DB::table('customer_company_details')->where('ref', $ref)->exists()) {
            return;
        }

        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $balanceBefore = (float) $company->balance;
        $company->balance = round($balanceBefore + $amount, 2);
        $company->save();

        DB::table('customer_company_details')->insert([
            'bank_id' => null,
            'customer_company_id' => $company->id,
            'ref' => $ref,
            'details' => $details,
            'type' => 'مصروف',
            'amount' => abs($amount),
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $company->balance,
            'date' => $date ?? date('Y-m-d'),
            'created_at' => now(),
            'user_id' => $userId,
        ]);
    }

    public function reverseCustomerCompanyByRef(string $ref): void
    {
        app(CustomerCompanyDirectCashSyncService::class)->reverseByRef($ref);
    }
}
