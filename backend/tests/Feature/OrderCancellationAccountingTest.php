<?php

namespace Tests\Feature;

use App\Enums\CollectionProviderType;
use App\Models\AccountEntry;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\TreeAccount;
use App\Services\Orders\OrderCancellationAccountingService;
use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderCancellationAccountingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $collectionParentAcc = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'OC' . substr(str_replace('.', '', uniqid('', true)), -8);
        $this->seedAccountingTree();
    }

    public function test_cancel_shopify_order_clears_collection_company_receivable(): void
    {
        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);

        $order = Order::withoutEvents(function () use ($orderSource) {
            $order = Order::create([
                'shopify_order_id' => 920001 + random_int(1, 99999),
                'customer_name' => 'عميل إلغاء ' . $this->codePrefix,
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
                'order_status' => 'طلب مؤكد',
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

        $gatewayLabel = 'PaymobTest_' . $this->codePrefix;
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
        $this->assertEquals(1050.0, (float) $order->prepaid_amount);

        $company = CollectionCompany::find($order->order_details->collection_provider_id);
        $this->assertNotNull($company);
        $collectReceivableId = (int) $company->receivable_tree_account_id;

        $debitBefore = (float) AccountEntry::where('order_id', $order->id)
            ->where('tree_account_id', $collectReceivableId)
            ->sum('debit');
        $this->assertEquals(1050.0, $debitBefore);

        $collectAccount = TreeAccount::find($collectReceivableId);
        $balanceBeforeCancel = (float) $collectAccount->balance;

        $cancelService = app(OrderCancellationAccountingService::class);
        $this->assertFalse($cancelService->requiresManualPrepaidRefundSelection($order->fresh(['order_details'])));
        $this->assertTrue($cancelService->isPrepaidHeldByCollectionIntermediary($order->fresh(['order_details'])));

        $cancelService->handleCancellation($order->fresh(['order_details']), 1);

        $remainingEntries = AccountEntry::where('order_id', $order->id)->count();
        $this->assertSame(0, $remainingEntries, 'All order GL entries must be removed on cancel');

        $collectAccount->refresh();
        $this->assertEqualsWithDelta(
            $balanceBeforeCancel - 1050.0,
            (float) $collectAccount->balance,
            0.02,
            'Collection receivable balance must drop by order amount after cancel'
        );

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertSame(CollectionProviderType::None->value, $od->collection_provider_type);
        $this->assertNull($od->collection_receivable_amount);
    }

    public function test_cancel_unpaid_shopify_order_does_not_recreate_customer_receivable(): void
    {
        $this->seedIndividualCustomersBucket();
        $orderSource = OrderSource::firstOrCreate(['name' => 'Shopify']);

        $order = Order::withoutEvents(function () use ($orderSource) {
            $order = Order::create([
                'shopify_order_id' => 930001 + random_int(1, 99999),
                'customer_name' => 'ياسمين اختبار ' . $this->codePrefix,
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
                'shipping_cost' => 180.0,
                'total_invoice' => 4785.0,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 4785.0,
                'order_status' => 'طلب مؤكد',
            ]);

            OrderProduct::create([
                'order_id' => $order->id,
                'category_id' => 1,
                'quantity' => 1,
                'price' => 4605.0,
                'total_price' => 4605.0,
            ]);

            OrderDetails::create([
                'order_id' => $order->id,
                'remaining_amount' => 4785,
                'amount_to_collect' => 4785,
            ]);

            return $order;
        });

        app(\App\Services\Accounting\SalesOrderAccountingService::class)
            ->recordInitialOrderRecognition($order->fresh(['order_products']));

        $this->assertGreaterThan(0, AccountEntry::where('order_id', $order->id)->count());
        $customerDebitBefore = (float) AccountEntry::where('order_id', $order->id)->sum('debit');
        $this->assertEqualsWithDelta(4785.0, $customerDebitBefore, 0.02);

        $staleDetails = OrderDetails::where('order_id', $order->id)->first();

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $staleDetails) {
            $cancelService = app(OrderCancellationAccountingService::class);
            $cancelService->handleCancellation($order->fresh(['order_details']), 1);

            $order->order_status = 'ملغي';
            $order->save();

            $staleDetails->reviewed = 0;
            $staleDetails->save();

            app(\App\Services\Shipping\OrderFinancialStateService::class)
                ->syncFromOrder($order->fresh(['order_details']), $staleDetails);
        });

        $order->refresh();
        app(\App\Services\Accounting\SalesOrderAccountingService::class)
            ->refreshOrderRecognition($order->fresh(['order_products', 'order_details']), true);

        $this->assertSame(0, AccountEntry::where('order_id', $order->id)->count(), 'Cancelled order must not keep or recreate GL entries');

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $od->remaining_amount, 0.02);
        $this->assertEqualsWithDelta(0.0, (float) $od->amount_to_collect, 0.02);
    }

    private function seedIndividualCustomersBucket(): void
    {
        $p = $this->codePrefix;
        $individuals = TreeAccount::create([
            'code' => $p . '1100',
            'name' => 'عملاء أفراد',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $this->collectionParentAcc?->parent_id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'customer_individual_parent_account_id'],
            ['value' => (string) $individuals->id]
        );
        \App\Models\Setting::updateOrCreate(
            ['key' => 'customer_online_parent_account_id'],
            ['value' => '']
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

        $this->collectionParentAcc = TreeAccount::create([
            'code' => $p . '1150',
            'name' => 'ذمم شركات التحصيل',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

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

        TreeAccount::create([
            'code' => $p . '4011001',
            'name' => 'إيرادات المبيعات',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'sales',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        TreeAccount::create([
            'code' => $p . '4012001',
            'name' => 'إيراد شحن',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'shipping_revenue',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }
}
