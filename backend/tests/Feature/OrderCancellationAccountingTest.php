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
