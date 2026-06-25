<?php

namespace Tests\Feature;

use App\Models\OrderSource;
use App\Models\Setting;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShopifyCustomerAccountLinkingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'SH' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_online_channel_customer_is_created_under_shopify_within_individuals(): void
    {
        $individualBucket = $this->seedIndividualCustomersBucket();
        $this->bindTestAccountSettings($individualBucket);
        $shopifySource = OrderSource::create(['name' => 'Shopify']);

        $linking = app(AccountLinkingService::class);
        $account = $linking->resolveOrderCustomerAccount(
            'فرد',
            'عميل شوبيفاي تجريبي ' . $this->codePrefix,
            '01099887766',
            null,
            $shopifySource->id
        );

        $this->assertNotNull($account);
        $shopifyParent = TreeAccount::find($account->parent_id);
        $this->assertNotNull($shopifyParent);
        $this->assertSame('Shopify', $shopifyParent->name);
        $this->assertSame($individualBucket->id, $shopifyParent->parent_id);
    }

    public function test_regular_individual_customer_stays_under_individuals_bucket(): void
    {
        $individualBucket = $this->seedIndividualCustomersBucket();
        $this->bindTestAccountSettings($individualBucket);

        $linking = app(AccountLinkingService::class);
        $account = $linking->resolveOrderCustomerAccount(
            'فرد',
            'عميل عادي اختبار ' . $this->codePrefix,
            '01011223344',
            null,
            null
        );

        $this->assertNotNull($account);
        $this->assertSame($individualBucket->id, $account->parent_id);
    }

    private function bindTestAccountSettings(TreeAccount $individualBucket): void
    {
        Setting::updateOrCreate(
            ['key' => 'customer_individual_parent_account_id'],
            ['value' => (string) $individualBucket->id]
        );
        Setting::updateOrCreate(
            ['key' => 'customer_online_parent_account_id'],
            ['value' => '']
        );
    }

    private function seedIndividualCustomersBucket(): TreeAccount
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

        $customersFolder = TreeAccount::create([
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
            'code' => $p . '1000234',
            'name' => 'عملاء أفراد',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $customersFolder->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }
}
