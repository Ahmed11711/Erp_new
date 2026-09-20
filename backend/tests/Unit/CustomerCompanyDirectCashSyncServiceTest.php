<?php

namespace Tests\Unit;

use App\Models\Bank;
use App\Models\TreeAccount;
use App\Models\User;
use App\Models\customerCompany;
use App\Services\Accounting\CustomerCompanyDirectCashSyncService;
use App\Services\Accounting\DirectCashTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerCompanyDirectCashSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'DW' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_bank_deposit_on_customer_company_tree_account_records_detail_and_reduces_balance(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        [$bank, $customerTree, $company] = $this->seedBankAndCustomerCompany(111720.0);

        $service = app(DirectCashTransactionService::class);
        $service->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $customerTree->id,
            'type' => 'receipt',
            'amount' => 111720,
            'date' => '2026-06-17',
            'notes' => 'سداد فرع العرب الجديدة للاستثمارات العقارية',
        ]);

        $company->refresh();
        $this->assertSame(0.0, (float) $company->balance);

        $detail = DB::table('customer_company_details')
            ->where('customer_company_id', $company->id)
            ->first();

        $this->assertNotNull($detail);
        $this->assertSame('تحصيل', $detail->type);
        $this->assertSame(111720.0, (float) $detail->amount);
        $this->assertSame(111720.0, (float) $detail->balance_before);
        $this->assertSame(0.0, (float) $detail->balance_after);
        $this->assertStringContainsString('BANK-DW-', (string) $detail->ref);
        $this->assertStringContainsString('إيداع بنكي', (string) $detail->details);
    }

    public function test_reverse_by_ref_restores_customer_company_balance(): void
    {
        $user = User::query()->first();
        $this->actingAs($user);

        [, $customerTree, $company] = $this->seedBankAndCustomerCompany(500.0);
        $sync = app(CustomerCompanyDirectCashSyncService::class);

        $sync->syncIfCustomerCompanyTreeAccount(
            $customerTree,
            'deposit',
            200,
            null,
            'BANK-DW-TEST',
            'إيداع بنكي [BANK-DW-TEST]',
            '2026-06-17',
            $user->id
        );

        $company->refresh();
        $this->assertSame(300.0, (float) $company->balance);

        $sync->reverseByRef('BANK-DW-TEST');

        $company->refresh();
        $this->assertSame(500.0, (float) $company->balance);
        $this->assertSame(0, DB::table('customer_company_details')->where('ref', 'BANK-DW-TEST')->count());
    }

    public function test_non_customer_company_counter_account_is_ignored(): void
    {
        $user = User::query()->first();
        $this->actingAs($user);

        [$bank, $expenseTree] = $this->seedBankAndExpenseAccount();

        $beforeCount = DB::table('customer_company_details')->count();

        app(DirectCashTransactionService::class)->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $expenseTree->id,
            'type' => 'receipt',
            'amount' => 100,
            'date' => '2026-06-17',
            'notes' => 'إيداع عام',
        ]);

        $this->assertSame($beforeCount, DB::table('customer_company_details')->count());
    }

    /**
     * @return array{0: Bank, 1: TreeAccount, 2: customerCompany}
     */
    private function seedBankAndCustomerCompany(float $openingBalance): array
    {
        $p = $this->codePrefix;

        $bankTree = TreeAccount::create([
            'code' => $p . 'BANK',
            'name' => 'بنك اختبار',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $customerTree = TreeAccount::create([
            'code' => $p . 'CUST',
            'name' => 'عميل شركة اختبار',
            'type' => 'asset',
            'level' => 5,
            'parent_id' => null,
            'balance' => $openingBalance,
            'debit_balance' => $openingBalance,
            'credit_balance' => 0,
        ]);

        $bank = Bank::create([
            'name' => 'بنك ' . $p,
            'type' => 'bank',
            'balance' => 0,
            'usage' => 'general',
            'asset_id' => $bankTree->id,
        ]);

        $company = customerCompany::create([
            'name' => 'شركة ' . $p,
            'phone1' => '01000000001',
            'governorate' => 'القاهرة',
            'address' => 'عنوان',
            'tree_account_id' => $customerTree->id,
            'balance' => $openingBalance,
        ]);

        return [$bank, $customerTree, $company];
    }

    /**
     * @return array{0: Bank, 1: TreeAccount}
     */
    private function seedBankAndExpenseAccount(): array
    {
        $p = $this->codePrefix;

        $bankTree = TreeAccount::create([
            'code' => $p . 'BANK2',
            'name' => 'بنك',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $expenseTree = TreeAccount::create([
            'code' => $p . 'EXP',
            'name' => 'مصروف',
            'type' => 'expense',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $bank = Bank::create([
            'name' => 'بنك ' . $p,
            'type' => 'bank',
            'balance' => 0,
            'usage' => 'general',
            'asset_id' => $bankTree->id,
        ]);

        return [$bank, $expenseTree];
    }
}
