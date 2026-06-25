<?php

namespace Tests\Feature;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\Setting;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CollectionCompanyVoucherTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $collectionParentAcc = null;

    private ?TreeAccount $safeAccount = null;

    private ?TreeAccount $bankAccount = null;

    private ?TreeAccount $serviceAccountTree = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'CV' . substr(str_replace('.', '', uniqid('', true)), -8);
        $this->seedAccountingTree();
    }

    public function test_collection_company_receipt_posts_gl_on_collection_account_not_shipping(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Paymob Test ' . $this->codePrefix,
            'status' => 'active',
        ]);
        $collectAccount = app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);
        $this->assertNotNull($collectAccount);

        $amount = 1500.0;
        $collectBalanceBefore = (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('debit')
            - (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('credit');
        $safeBalanceBefore = (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('debit')
            - (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('credit');

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => $amount,
            'notes' => 'تحصيل من شركة التحصيل',
        ]);

        $response->assertCreated();

        $collectBalanceAfter = (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('debit')
            - (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('credit');
        $safeBalanceAfter = (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('debit')
            - (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('credit');

        $this->assertEquals(
            round($collectBalanceBefore - $amount, 2),
            round($collectBalanceAfter, 2),
            'Collection company receivable entries must net down on receipt'
        );
        $this->assertEquals(
            round($safeBalanceBefore + $amount, 2),
            round($safeBalanceAfter, 2),
            'Safe entries must net up on receipt'
        );

        $voucherId = (int) $response->json('data.id');
        $entries = AccountEntry::where('voucher_id', $voucherId)->get();
        $this->assertCount(2, $entries);

        $debitSafe = (float) $entries->where('tree_account_id', $this->safeAccount->id)->sum('debit');
        $creditCollect = (float) $entries->where('tree_account_id', $collectAccount->id)->sum('credit');

        $this->assertEquals($amount, $debitSafe);
        $this->assertEquals($amount, $creditCollect);
    }

    public function test_collection_company_payment_debits_receivable_and_credits_safe(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Sympl Test ' . $this->codePrefix,
            'status' => 'active',
        ]);
        $collectAccount = app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);
        $this->assertNotNull($collectAccount);

        $amount = 800.0;

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'payment',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => $amount,
            'notes' => 'صرف لشركة التحصيل',
        ]);

        $response->assertCreated();

        $debitCollect = (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('debit');
        $creditCollect = (float) AccountEntry::where('tree_account_id', $collectAccount->id)->sum('credit');
        $debitSafe = (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('debit');
        $creditSafe = (float) AccountEntry::where('tree_account_id', $this->safeAccount->id)->sum('credit');

        $this->assertEquals($amount, round($debitCollect, 2));
        $this->assertEquals($amount, round($creditSafe, 2));
        $this->assertEquals(0.0, round($creditCollect, 2));
        $this->assertEquals(0.0, round($debitSafe, 2));

        $voucherId = (int) $response->json('data.id');
        $voucherDebitCollect = (float) AccountEntry::where('voucher_id', $voucherId)
            ->where('tree_account_id', $collectAccount->id)
            ->sum('debit');
        $voucherCreditSafe = (float) AccountEntry::where('voucher_id', $voucherId)
            ->where('tree_account_id', $this->safeAccount->id)
            ->sum('credit');

        $this->assertEquals($amount, $voucherDebitCollect);
        $this->assertEquals($amount, $voucherCreditSafe);
    }

    public function test_rejects_amount_exceeding_selected_orders_for_collection_company(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Mismatch ' . $this->codePrefix,
            'status' => 'active',
        ]);
        app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => 999,
            'settled_order_ids' => [999999991],
        ]);

        $response->assertStatus(422);
    }

    public function test_partial_collection_receipt_reduces_pending_receivable(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);
        $order = Order::withoutEvents(function () use ($orderSource) {
            $order = Order::create([
                'shopify_order_id' => 940001 + random_int(1, 99999),
                'customer_name' => 'عميل جزئي ' . $this->codePrefix,
                'customer_type' => 'فرد',
                'customer_phone_1' => '010' . substr($this->codePrefix, -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'address' => 'شارع تجريبي',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => $orderSource->id,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 2000.0,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 2000.0,
                'order_status' => 'طلب جديد',
            ]);

            OrderProduct::create([
                'order_id' => $order->id,
                'category_id' => 1,
                'quantity' => 1,
                'price' => 2000.0,
                'total_price' => 2000.0,
            ]);

            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });

        app(ShopifyOrderImportService::class)->applyFinancialFieldsFromPayload($order, [
            'financial_status' => 'paid',
            'total_price' => '2000.00',
            'total_line_items_price' => '2000.00',
            'total_tax' => '0.00',
            'total_discounts' => '0.00',
            'payment_gateway_names' => ['PartialTest_' . $this->codePrefix],
            'shipping_lines' => [['price' => '0.00']],
        ]);

        $order->refresh()->load('order_details');
        $company = CollectionCompany::find($order->order_details->collection_provider_id);
        $this->assertNotNull($company);

        $partialAmount = 700.0;
        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => $partialAmount,
            'settled_order_ids' => [$order->id],
        ]);

        $response->assertCreated();

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertEquals(1300.0, round((float) $od->collection_receivable_amount, 2));
        $this->assertSame(OrderSettlementStatus::Partial->value, $od->settlement_status);
        $this->assertSame(OrderCollectionStatus::Partial->value, $od->collection_status);
        $this->assertNotSame('تم التحصيل', $order->fresh()->order_status);

        $collectAccount = TreeAccount::find($company->receivable_tree_account_id);
        $this->assertEquals(1300.0, round((float) $collectAccount->balance, 2));
    }

    public function test_rejects_collection_company_voucher_without_company_id(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'account_id' => $this->safeAccount->id,
            'amount' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_collection_receipt_via_bank_updates_operational_and_gl_balances(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Bank Collect ' . $this->codePrefix,
            'status' => 'active',
        ]);
        $collectAccount = app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);
        $amount = 600.0;
        $bank = Bank::where('asset_id', $this->bankAccount->id)->first();
        $this->assertNotNull($bank);

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->bankAccount->id,
            'amount' => $amount,
        ]);

        $response->assertCreated();
        $bank->refresh();
        $this->assertEquals($amount, round((float) $bank->balance, 2));

        $voucherId = (int) $response->json('data.id');
        $this->assertEquals(
            $amount,
            (float) AccountEntry::where('voucher_id', $voucherId)->where('tree_account_id', $this->bankAccount->id)->sum('debit')
        );
        $this->assertEquals(
            $amount,
            (float) AccountEntry::where('voucher_id', $voucherId)->where('tree_account_id', $collectAccount->id)->sum('credit')
        );
    }

    public function test_collection_receipt_via_service_account_updates_operational_balance(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Svc Collect ' . $this->codePrefix,
            'status' => 'active',
        ]);
        app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);
        $amount = 450.0;
        $svc = ServiceAccount::where('account_id', $this->serviceAccountTree->id)->first();
        $this->assertNotNull($svc);

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->serviceAccountTree->id,
            'amount' => $amount,
        ]);

        $response->assertCreated();
        $svc->refresh();
        $this->assertEquals($amount, round((float) $svc->balance, 2));
    }

    public function test_rejects_receivable_account_as_payment_source(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Bad Source ' . $this->codePrefix,
            'status' => 'active',
        ]);
        $collectAccount = app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $collectAccount->id,
            'amount' => 100,
        ]);

        $response->assertStatus(422);
    }

    public function test_collection_receipt_with_settled_orders_updates_tree_account_balance(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);
        $order = Order::withoutEvents(function () use ($orderSource) {
            $order = Order::create([
                'shopify_order_id' => 930001 + random_int(1, 99999),
                'customer_name' => 'عميل تحصيل ' . $this->codePrefix,
                'customer_type' => 'فرد',
                'customer_phone_1' => '010' . substr($this->codePrefix, -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'address' => 'شارع تجريبي',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => $orderSource->id,
                'order_type' => 'جديد',
                'shipping_cost' => 50.0,
                'total_invoice' => 1050.0,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 1050.0,
                'order_status' => 'طلب جديد',
            ]);

            OrderProduct::create([
                'order_id' => $order->id,
                'category_id' => 1,
                'quantity' => 1,
                'price' => 1000.0,
                'total_price' => 1000.0,
            ]);

            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });

        $gatewayLabel = 'SettleTest_' . $this->codePrefix;
        app(ShopifyOrderImportService::class)->applyFinancialFieldsFromPayload($order, [
            'financial_status' => 'paid',
            'total_price' => '1050.00',
            'total_line_items_price' => '1000.00',
            'total_tax' => '0.00',
            'total_discounts' => '0.00',
            'payment_gateway_names' => [$gatewayLabel],
            'shipping_lines' => [['price' => '50.00']],
        ]);

        $order->refresh()->load('order_details');
        $company = CollectionCompany::find($order->order_details->collection_provider_id);
        $this->assertNotNull($company);
        $collectAccount = TreeAccount::find($company->receivable_tree_account_id);
        $this->assertNotNull($collectAccount);
        $balanceBefore = (float) $collectAccount->balance;
        $this->assertEquals(1050.0, $balanceBefore);

        $amount = 1050.0;
        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'receipt',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => $amount,
            'settled_order_ids' => [$order->id],
        ]);

        $response->assertCreated();

        $collectAccount->refresh();
        $this->assertEquals(0.0, round((float) $collectAccount->balance, 2));
        $this->assertSame('تم التحصيل', $order->fresh()->order_status);
    }

    public function test_collection_payment_increases_tree_receivable_balance(): void
    {
        $user = User::factory()->create(['department' => 'Financial Accounts']);
        $this->actingAs($user, 'api');

        $company = CollectionCompany::create([
            'name' => 'Refund Test ' . $this->codePrefix,
            'status' => 'active',
        ]);
        $collectAccount = app(AccountLinkingService::class)->ensureCollectionCompanyAccount($company);
        $amount = 500.0;

        $response = $this->postJson('/api/accounting/vouchers/', [
            'date' => now()->toDateString(),
            'type' => 'payment',
            'voucher_type' => 'collection_company',
            'collection_company_id' => $company->id,
            'account_id' => $this->safeAccount->id,
            'amount' => $amount,
        ]);

        $response->assertCreated();

        $collectAccount->refresh();
        $this->assertEquals($amount, round((float) $collectAccount->balance, 2));

        $voucherId = (int) $response->json('data.id');
        $this->assertEquals(
            $amount,
            (float) AccountEntry::where('voucher_id', $voucherId)->where('tree_account_id', $collectAccount->id)->sum('debit')
        );
    }

    private function seedAccountingTree(): void
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

        $cashParent = TreeAccount::create([
            'code' => $p . '1100',
            'name' => 'النقدية',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->safeAccount = TreeAccount::create([
            'code' => $p . '1101',
            'name' => 'خزينة اختبار',
            'type' => 'asset',
            'detail_type' => 'safe',
            'level' => 3,
            'parent_id' => $cashParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        Safe::create([
            'name' => 'خزينة اختبار ' . $p,
            'balance' => 0,
            'type' => 'main',
            'account_id' => $this->safeAccount->id,
        ]);

        $this->bankAccount = TreeAccount::create([
            'code' => $p . '1102',
            'name' => 'بنك اختبار',
            'type' => 'asset',
            'detail_type' => 'bank',
            'level' => 3,
            'parent_id' => $cashParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        Bank::create([
            'name' => 'بنك اختبار ' . $p,
            'balance' => 0,
            'type' => 'main',
            'usage' => 'test',
            'asset_id' => $this->bankAccount->id,
        ]);

        $this->serviceAccountTree = TreeAccount::create([
            'code' => $p . '1103',
            'name' => 'حساب خدمي اختبار',
            'type' => 'asset',
            'detail_type' => 'service_account',
            'level' => 3,
            'parent_id' => $cashParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        ServiceAccount::create([
            'name' => 'خدمي اختبار ' . $p,
            'balance' => 0,
            'account_id' => $this->serviceAccountTree->id,
        ]);

        $debtors = TreeAccount::create([
            'code' => $p . '1200',
            'name' => 'المدينون',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->collectionParentAcc = TreeAccount::create([
            'code' => $p . '1202',
            'name' => 'شركات التحصيل',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $debtors->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        Setting::updateOrCreate(
            ['key' => 'collection_companies_parent_account_id'],
            ['value' => (string) $this->collectionParentAcc->id]
        );
    }
}
