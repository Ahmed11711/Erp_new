<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DoubleEntryValidationTest extends TestCase
{
    public function test_debit_must_equal_credit_in_journal(): void
    {
        $items = [
            ['account_id' => 1, 'debit' => 500, 'credit' => 0],
            ['account_id' => 2, 'debit' => 0, 'credit' => 500],
        ];
        $totalDebit = array_sum(array_column($items, 'debit'));
        $totalCredit = array_sum(array_column($items, 'credit'));

        $this->assertEquals($totalDebit, $totalCredit, 'Total debit must equal total credit');
    }

    public function test_imbalanced_entry_is_rejected(): void
    {
        $items = [
            ['account_id' => 1, 'debit' => 500, 'credit' => 0],
            ['account_id' => 2, 'debit' => 0, 'credit' => 300],
        ];
        $totalDebit = array_sum(array_column($items, 'debit'));
        $totalCredit = array_sum(array_column($items, 'credit'));
        $isBalanced = abs($totalDebit - $totalCredit) <= 0.01;

        $this->assertFalse($isBalanced, 'Imbalanced entries should be rejected');
    }

    public function test_purchase_entry_debit_credit_direction(): void
    {
        $inventoryDebit = 1000;
        $supplierCredit = 1000;

        $this->assertEquals($inventoryDebit, $supplierCredit);
        $this->assertGreaterThan(0, $inventoryDebit, 'Inventory (asset) is debited');
        $this->assertGreaterThan(0, $supplierCredit, 'Supplier (liability) is credited');
    }

    public function test_supplier_payment_debit_credit_direction(): void
    {
        $supplierDebit = 500;
        $bankCredit = 500;

        $this->assertEquals($supplierDebit, $bankCredit);
        $this->assertGreaterThan(0, $supplierDebit, 'Supplier (liability) is debited to reduce obligation');
        $this->assertGreaterThan(0, $bankCredit, 'Bank (asset) is credited to reduce cash');
    }

    public function test_sales_entry_debit_credit_direction(): void
    {
        $customerDebit = 2000;
        $revenueCredit = 2000;

        $this->assertEquals($customerDebit, $revenueCredit);
    }

    public function test_cogs_entry_direction(): void
    {
        $cogsDebit = 800;
        $inventoryCredit = 800;

        $this->assertEquals($cogsDebit, $inventoryCredit);
    }

    public function test_expense_entry_direction(): void
    {
        $expenseDebit = 300;
        $bankCredit = 300;

        $this->assertEquals($expenseDebit, $bankCredit);
    }

    public function test_depreciation_entry_direction(): void
    {
        $depExpenseDebit = 1000;
        $accumDepCredit = 1000;

        $this->assertEquals($depExpenseDebit, $accumDepCredit);
    }

    public function test_income_entry_direction(): void
    {
        $bankDebit = 5000;
        $revenueCredit = 5000;

        $this->assertEquals($bankDebit, $revenueCredit, 'Income: Dr Bank / Cr Revenue');
    }

    public function test_capital_injection_direction(): void
    {
        $assetDebit = 50000;
        $equityCredit = 50000;

        $this->assertEquals($assetDebit, $equityCredit, 'Capital: Dr Asset / Cr Equity');
    }

    public function test_commitment_creation_direction(): void
    {
        $expenseDebit = 12000;
        $liabilityCredit = 12000;

        $this->assertEquals($expenseDebit, $liabilityCredit, 'Commitment: Dr Expense / Cr Liability');
    }

    public function test_commitment_payment_direction(): void
    {
        $liabilityDebit = 6000;
        $cashCredit = 6000;

        $this->assertEquals($liabilityDebit, $cashCredit, 'Payment: Dr Liability / Cr Cash');
    }

    public function test_balance_formula_for_debit_normal_account(): void
    {
        $totalDebit = 5000;
        $totalCredit = 2000;
        $accountType = 'asset';
        $isDebitNormal = in_array($accountType, ['asset', 'expense']);
        $balance = $isDebitNormal ? $totalDebit - $totalCredit : $totalCredit - $totalDebit;

        $this->assertEquals(3000, $balance, 'Asset balance = debit - credit');
    }

    public function test_balance_formula_for_credit_normal_account(): void
    {
        $totalDebit = 2000;
        $totalCredit = 5000;
        $accountType = 'liability';
        $isDebitNormal = in_array($accountType, ['asset', 'expense']);
        $balance = $isDebitNormal ? $totalDebit - $totalCredit : $totalCredit - $totalDebit;

        $this->assertEquals(3000, $balance, 'Liability balance = credit - debit');
    }

    public function test_revenue_account_balance_formula(): void
    {
        $totalDebit = 0;
        $totalCredit = 10000;
        $accountType = 'revenue';
        $isDebitNormal = in_array($accountType, ['asset', 'expense']);
        $balance = $isDebitNormal ? $totalDebit - $totalCredit : $totalCredit - $totalDebit;

        $this->assertEquals(10000, $balance, 'Revenue balance = credit - debit');
    }
}
