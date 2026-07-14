<?php

namespace App\Services\Accounting;

use App\Models\customerCompany;
use App\Models\ShippingCompany;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

/**
 * مزامنة الأرصدة التشغيلية للحسابات المدينة في المصروف (مورد، عميل، مندوب/شحن، بنك، خزينة، …).
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
        $this->syncShippingCompanyIfLinked($treeAccountId, $amount, $ref, $details, $date, $reverse);
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

    /**
     * عند اختيار حساب ذمم المندوب/شركة الشحن (receivable) كحساب مدين للمصروف:
     * يزيد الرصيد التشغيلي (المندوب مدين للشركة) ويُسجَّل في shipping_company_details.
     * حساب tree_account_id (ذمم دائن/مستحق للمندوب) يتأثر عبر الشجرة فقط ويظهر في displayBalance.
     */
    private function syncShippingCompanyIfLinked(
        int $treeAccountId,
        float $amount,
        string $ref,
        string $details,
        ?string $date,
        bool $reverse
    ): void {
        $company = ShippingCompany::query()
            ->where('receivable_tree_account_id', $treeAccountId)
            ->lockForUpdate()
            ->first();

        if (! $company) {
            return;
        }

        if ($reverse) {
            $this->reverseShippingCompanyByRef($ref);

            return;
        }

        if (DB::table('shipping_company_details')->where('ref', $ref)->exists()) {
            return;
        }

        $rounded = round($amount, 3);
        $balanceBefore = (float) $company->balance;
        $company->balance = round($balanceBefore + $rounded, 3);
        $company->save();

        $userName = auth()->user()?->name ?? 'system';
        $entryDate = $date ?? date('Y-m-d');

        DB::table('shipping_company_details')->insert([
            'order_id' => null,
            'voucher_id' => null,
            'shipping_date' => $entryDate,
            'collect_date' => $entryDate,
            'status' => 'مصروف',
            'amount' => $rounded,
            'shipping_company_id' => $company->id,
            'ref' => $ref,
            'by' => $userName,
            'is_done' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reverseShippingCompanyByRef(string $ref): void
    {
        $detail = DB::table('shipping_company_details')->where('ref', $ref)->first();
        if (! $detail) {
            return;
        }

        $company = ShippingCompany::query()
            ->whereKey((int) $detail->shipping_company_id)
            ->lockForUpdate()
            ->first();

        if ($company) {
            $company->balance = round((float) $company->balance - (float) $detail->amount, 3);
            $company->save();
        }

        DB::table('shipping_company_details')->where('id', $detail->id)->delete();
    }
}
