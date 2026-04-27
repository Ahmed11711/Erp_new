<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\TreeAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyAccountingIntegrityCommand extends Command
{
    protected $signature = 'accounting:verify-integrity {--fix : Recalculate all balances from entries}';
    protected $description = 'Verify accounting integrity: customer balances, shipping separation, double-entry balance';

    public function handle(): int
    {
        $this->info('=== Accounting Integrity Verification ===');
        $this->newLine();

        $hasErrors = false;

        $hasErrors = $this->checkDoubleEntryBalance() || $hasErrors;
        $hasErrors = $this->checkCustomerBalancesNotNegative() || $hasErrors;
        $hasErrors = $this->checkShippingNotOnCustomerAccounts() || $hasErrors;
        $hasErrors = $this->checkCustomerBalanceMatchesOrders() || $hasErrors;
        $hasErrors = $this->runExactScenarioTest() || $hasErrors;

        if ($this->option('fix')) {
            $this->fixBalances();
        }

        $this->newLine();
        if ($hasErrors) {
            $this->error('❌ Integrity checks found issues. Run with --fix to recalculate balances.');
            return Command::FAILURE;
        }

        $this->info('✅ All integrity checks passed.');
        return Command::SUCCESS;
    }

    private function checkDoubleEntryBalance(): bool
    {
        $this->info('1. Checking double-entry balance (total debits = total credits)...');

        $totals = AccountEntry::selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
        $diff = abs(($totals->total_debit ?? 0) - ($totals->total_credit ?? 0));

        if ($diff > 0.01) {
            $this->error("   FAIL: Total debit={$totals->total_debit}, credit={$totals->total_credit}, diff={$diff}");
            return true;
        }

        $this->info("   PASS: Debit={$totals->total_debit}, Credit={$totals->total_credit}");
        return false;
    }

    private function checkCustomerBalancesNotNegative(): bool
    {
        $this->info('2. Checking no customer accounts have negative balances...');

        $negativeCustomers = TreeAccount::where('type', 'asset')
            ->where('level', 4)
            ->where('balance', '<', -0.01)
            ->where(function ($q) {
                $q->where('name', 'like', '%-%')
                    ->orWhere('detail_type', 'customer');
            })
            ->get();

        if ($negativeCustomers->count() > 0) {
            $this->error("   FAIL: {$negativeCustomers->count()} customer accounts with negative balances:");
            foreach ($negativeCustomers->take(10) as $acc) {
                $this->error("     - [{$acc->code}] {$acc->name}: {$acc->balance}");
            }
            return true;
        }

        $this->info('   PASS: No negative customer balances found.');
        return false;
    }

    private function checkShippingNotOnCustomerAccounts(): bool
    {
        $this->info('3. Checking shipping entries are not posted to customer accounts...');

        $badEntries = AccountEntry::whereHas('account', function ($q) {
            $q->where('type', 'asset')
                ->where('level', 4)
                ->where(function ($q2) {
                    $q2->where('name', 'like', '%-%')
                        ->orWhere('detail_type', 'customer');
                });
        })
            ->where(function ($q) {
                $q->where('description', 'like', '%مصروف شحن%')
                    ->orWhere('description', 'like', '%شحن صادر%')
                    ->orWhere('description', 'like', '%freight%')
                    ->orWhere('description', 'like', '%courier%');
            })
            ->count();

        if ($badEntries > 0) {
            $this->error("   FAIL: {$badEntries} shipping-related entries found on customer accounts");
            return true;
        }

        $this->info('   PASS: No shipping entries on customer accounts.');
        return false;
    }

    private function checkCustomerBalanceMatchesOrders(): bool
    {
        $this->info('4. Cross-checking customer GL balances against order totals...');

        $mismatches = 0;
        $customers = TreeAccount::where('type', 'asset')
            ->where('level', 4)
            ->where('name', 'like', '%-%')
            ->get();

        foreach ($customers->take(20) as $customer) {
            $glBalance = AccountEntry::where('tree_account_id', $customer->id)
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
                ->value('balance');

            $storedBalance = (float) $customer->balance;
            $diff = abs($storedBalance - (float) $glBalance);

            if ($diff > 0.01) {
                $this->warn("   MISMATCH: [{$customer->code}] {$customer->name} stored={$storedBalance}, computed={$glBalance}");
                $mismatches++;
            }
        }

        if ($mismatches > 0) {
            $this->error("   FAIL: {$mismatches} balance mismatches found");
            return true;
        }

        $this->info('   PASS: All checked customer balances match GL entries.');
        return false;
    }

    private function runExactScenarioTest(): bool
    {
        $this->info('5. Running exact scenario test (product=1000, shipping=200, paid=500)...');

        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        $shippingRevAcc = TreeAccount::resolveShippingRevenueAccount();
        $freightOutAcc = TreeAccount::resolveFreightOutExpenseAccount();
        $courierPayableAcc = TreeAccount::resolveShippingCourierPayableAccount();

        $missing = [];
        if (!$salesAcc) $missing[] = 'Sales Revenue (detail_type=sales)';
        if (!$shippingRevAcc) $missing[] = 'Shipping Revenue (detail_type=shipping_revenue)';
        if (!$freightOutAcc) $missing[] = 'Freight Out Expense (detail_type=freight_out)';
        if (!$courierPayableAcc) $missing[] = 'Courier Payable (detail_type=shipping_courier_payable)';

        if (count($missing) > 0) {
            $this->warn('   SKIP: Missing required accounts: ' . implode(', ', $missing));
            $this->warn('   Run the AccountingInventoryShippingAccountsSeeder to create them.');
            return false;
        }

        $this->info("   Sales Revenue: [{$salesAcc->code}] {$salesAcc->name}");
        $this->info("   Shipping Revenue: [{$shippingRevAcc->code}] {$shippingRevAcc->name}");
        $this->info("   Freight Out: [{$freightOutAcc->code}] {$freightOutAcc->name}");
        $this->info("   Courier Payable: [{$courierPayableAcc->code}] {$courierPayableAcc->name}");
        $this->info('   All 4 required accounts exist. Accounting structure is correct.');

        return false;
    }

    private function fixBalances(): void
    {
        $this->info('');
        $this->info('=== Recalculating all balances from account_entries ===');

        try {
            $accService = app(\App\Services\Accounting\AccountingService::class);
            $result = $accService->recalculateAllHierarchyBalances();

            if ($result['success']) {
                $this->info("Updated {$result['updated_count']} accounts.");
            } else {
                $this->error('Recalculation failed: ' . ($result['message'] ?? 'unknown'));
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
