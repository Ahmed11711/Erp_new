<?php

namespace Tests\Feature;

use App\Models\TreeAccount;
use App\Models\customerCompany;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerCompanyAccountLinkingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'CC' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_ensure_customer_company_account_creates_child_under_corporate_bucket(): void
    {
        $corporateBucket = $this->seedCorporateCustomersBucket();
        $company = customerCompany::create([
            'name' => 'شركة اختبار ' . $this->codePrefix,
            'phone1' => '01' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'governorate' => 'القاهرة',
            'address' => 'عنوان تجريبي',
        ]);

        $linking = app(AccountLinkingService::class);
        $account = $linking->ensureCustomerCompanyAccount($company);

        $this->assertNotNull($account);
        $company->refresh();
        $this->assertSame($account->id, $company->tree_account_id);
        $this->assertSame($corporateBucket->id, $account->parent_id);
        $this->assertSame('asset', $account->type);
    }

    private function seedCorporateCustomersBucket(): TreeAccount
    {
        $p = $this->codePrefix;
        $root = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'الأصول',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $currentAssets = TreeAccount::create([
            'code' => $p . '10002',
            'name' => 'الأصول المتداولة',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $receivables = TreeAccount::create([
            'code' => $p . '100023',
            'name' => 'العملاء',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $currentAssets->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        return TreeAccount::create([
            'code' => $p . '1000235',
            'name' => 'عملاء شركات',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $receivables->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }
}
