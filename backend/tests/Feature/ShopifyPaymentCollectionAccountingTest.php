<?php

namespace Tests\Feature;

use App\Enums\CollectionProviderType;
use App\Models\AccountEntry;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\Setting;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountLinkingService;
use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShopifyPaymentCollectionAccountingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $salesRevenueAcc = null;

    private ?TreeAccount $collectionParentAcc = null;

    private ?TreeAccount $customerParentAcc = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'SP' . substr(str_replace('.', '', uniqid('', true)), -8);
        $this->seedAccountingTree();
    }

    public function test_paid_shopify_order_posts_receivable_on_matching_collection_company(): void
    {
        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);

        $order = Order::withoutEvents(function () use ($orderSource) {
            $order = Order::create([
                'shopify_order_id' => 900001 + random_int(1, 99999),
                'customer_name' => 'عميل شوبيفاي ' . $this->codePrefix,
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

        $gatewayLabel = 'GatewayTest_' . $this->codePrefix;
        $payload = [
            'financial_status' => 'paid',
            'total_price' => '1050.00',
            'total_line_items_price' => '1000.00',
            'total_tax' => '0.00',
            'total_discounts' => '0.00',
            'payment_gateway_names' => [$gatewayLabel],
            'shipping_lines' => [
                ['price' => '50.00'],
            ],
        ];

        $parentBefore = (float) $this->collectionParentAcc->balance;

        app(ShopifyOrderImportService::class)->applyFinancialFieldsFromPayload($order, $payload);

        $order->refresh()->load('order_details');
        $this->assertEquals(1050.0, (float) $order->prepaid_amount);
        $this->assertEquals(0.0, (float) $order->net_total);

        $od = $order->order_details;
        $this->assertNotNull($od);
        $this->assertSame(CollectionProviderType::CollectionCompany->value, $od->collection_provider_type);

        $company = CollectionCompany::find($od->collection_provider_id);
        $this->assertNotNull($company);
        $this->assertSame($gatewayLabel, $company->name);
        $this->assertNotNull($company->receivable_tree_account_id);

        $collectReceivableId = (int) $company->receivable_tree_account_id;
        $collectDebit = (float) AccountEntry::where('order_id', $order->id)
            ->where('tree_account_id', $collectReceivableId)
            ->sum('debit');
        $collectCredit = (float) AccountEntry::where('order_id', $order->id)
            ->where('tree_account_id', $collectReceivableId)
            ->sum('credit');

        $this->assertEquals(1050.0, $collectDebit, 'Receivable must be debited on collection company account');
        $this->assertEquals(0.0, $collectCredit, 'Collection company receivable must stay open (no cash settlement on import)');

        $customerAccountIds = TreeAccount::where('parent_id', $this->customerParentAcc->id)->pluck('id');
        $customerDebit = (float) AccountEntry::where('order_id', $order->id)
            ->whereIn('tree_account_id', $customerAccountIds)
            ->sum('debit');
        $this->assertEquals(0.0, $customerDebit, 'Customer receivable must not carry Shopify prepaid amount');

        $prepaidCashEntries = AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-PREPAID-' . $order->id . '-%')
            ->count();
        $this->assertSame(0, $prepaidCashEntries, 'Shopify prepaid must not post cash/bank journal on import');

        $collectAccount = TreeAccount::find($collectReceivableId);
        $this->assertNotNull($collectAccount);
        $this->assertSame($this->collectionParentAcc->id, (int) $collectAccount->parent_id);
        $this->assertEquals($collectDebit, (float) $collectAccount->balance, 'Leaf balance must equal order entries');
        $this->assertEquals($collectDebit, (float) $collectAccount->debit_balance);

        $this->collectionParentAcc->refresh();
        $this->assertEquals(
            round($parentBefore + $collectDebit, 2),
            (float) $this->collectionParentAcc->balance,
            'Collection parent must include new child receivable'
        );
    }

    public function test_full_import_flow_updates_tree_balances_not_customer_account(): void
    {
        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);
        Setting::updateOrCreate(
            ['key' => 'customer_online_parent_account_id'],
            ['value' => '']
        );

        $shopifyOrderId = 910000 + random_int(1, 99999);
        $gatewayLabel = 'GatewayTree_' . $this->codePrefix;
        $payload = [
            'id' => $shopifyOrderId,
            'financial_status' => 'paid',
            'total_price' => '500.00',
            'total_line_items_price' => '500.00',
            'total_tax' => '0.00',
            'total_discounts' => '0.00',
            'payment_gateway_names' => [$gatewayLabel],
            'shipping_lines' => [],
            'customer' => [
                'first_name' => 'اختبار',
                'last_name' => 'شجرة',
            ],
            'shipping_address' => [
                'first_name' => 'اختبار',
                'last_name' => 'شجرة',
                'phone' => '010' . substr($this->codePrefix, -8),
                'city' => 'القاهرة',
                'province' => 'القاهرة',
                'address1' => 'شارع',
            ],
            'line_items' => [
                [
                    'id' => 1,
                    'title' => 'منتج تجريبي',
                    'quantity' => 1,
                    'price' => '500.00',
                    'sku' => '',
                ],
            ],
        ];

        $order = Order::withoutEvents(function () use ($orderSource, $shopifyOrderId, $payload) {
            $order = Order::create([
                'shopify_order_id' => $shopifyOrderId,
                'customer_name' => 'اختبار شجرة',
                'customer_type' => 'فرد',
                'customer_phone_1' => data_get($payload, 'shipping_address.phone'),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'شارع',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => $orderSource->id,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 500.0,
                'prepaid_amount' => 500.0,
                'discount' => 0,
                'net_total' => 0,
                'order_status' => 'طلب جديد',
            ]);

            OrderProduct::create([
                'order_id' => $order->id,
                'category_id' => 1,
                'quantity' => 1,
                'price' => 500.0,
                'total_price' => 500.0,
            ]);

            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });

        app(\App\Services\Accounting\SalesOrderAccountingService::class)->recordInitialOrderRecognition($order);

        $customerAccount = app(AccountLinkingService::class)->resolveOrderCustomerAccount(
            $order->customer_type,
            $order->customer_name,
            $order->customer_phone_1,
            null,
            $order->order_source_id
        );
        $this->assertNotNull($customerAccount);
        $customerAccount->refresh();
        $this->assertEquals(500.0, (float) $customerAccount->balance, 'Initial recognition posts on customer before collection mapping');

        app(ShopifyOrderImportService::class)->applyFinancialFieldsFromPayload($order, $payload);

        $order->refresh()->load('order_details');
        $company = CollectionCompany::find($order->order_details->collection_provider_id);
        $this->assertNotNull($company);

        $collectAccount = TreeAccount::find($company->receivable_tree_account_id);
        $this->assertNotNull($collectAccount);
        $collectAccount->refresh();
        $customerAccount->refresh();

        $orderCollectDebit = (float) AccountEntry::where('order_id', $order->id)
            ->where('tree_account_id', $collectAccount->id)
            ->sum('debit');

        $this->assertEquals(500.0, $orderCollectDebit);
        $this->assertEquals(500.0, (float) $collectAccount->balance, 'Collection company leaf must show receivable in tree');
        $this->assertSame($this->collectionParentAcc->id, (int) $collectAccount->parent_id);
        $this->assertEquals(0.0, (float) $customerAccount->balance, 'Customer tree balance must be cleared after Shopify remapping');
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

        $currentAssets = TreeAccount::create([
            'code' => $p . '1100',
            'name' => 'الأصول المتداولة',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $debtors = TreeAccount::create([
            'code' => $p . '1200',
            'name' => 'المدينون',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $currentAssets->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->customerParentAcc = TreeAccount::create([
            'code' => $p . '1201',
            'name' => 'عملاء أفراد',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $debtors->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->collectionParentAcc = TreeAccount::create([
            'code' => $p . '1202',
            'name' => 'شركات التحصيل',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $debtors->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        Setting::updateOrCreate(
            ['key' => 'collection_companies_parent_account_id'],
            ['value' => (string) $this->collectionParentAcc->id]
        );
        Setting::updateOrCreate(
            ['key' => 'customer_individual_parent_account_id'],
            ['value' => (string) $this->customerParentAcc->id]
        );

        $revenueRoot = TreeAccount::create([
            'code' => $p . '4000',
            'name' => 'الإيرادات',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->salesRevenueAcc = TreeAccount::create([
            'code' => $p . '4010',
            'name' => 'إيرادات المبيعات',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'sales',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }
}
