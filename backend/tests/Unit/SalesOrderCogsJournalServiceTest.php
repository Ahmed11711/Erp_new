<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderShipment;
use App\Models\OrderShipmentLine;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\MissingSalesInvoiceJournalService;
use App\Services\Accounting\SalesOrderCogsJournalService;
use App\Services\Accounting\SalesOrderLifecycleJournalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesOrderCogsJournalServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private ?TreeAccount $cogsAcc = null;

    private ?TreeAccount $inventoryAcc = null;

    private ?Category $category = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'Z'.substr(str_replace('.', '', uniqid('', true)), -10);
        $this->seedAccountsAndCategory();
    }

    public function test_posts_cogs_from_shipped_quantity_and_average_cost(): void
    {
        $order = $this->makeShippedOrder(2, 50);

        $result = app(SalesOrderCogsJournalService::class)->postIfMissing($order);

        $this->assertSame('posted', $result);
        $debit = (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'COGS-'.$order->id.'-%')
            ->sum('debit');
        $credit = (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'COGS-'.$order->id.'-%')
            ->sum('credit');
        $this->assertEquals(100.0, $debit);
        $this->assertEquals(100.0, $credit);
    }

    public function test_preview_marks_zero_cost_as_not_applicable(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $this->zeroCategoryCost();

        $preview = app(SalesOrderCogsJournalService::class)->preview($order->fresh(['order_products', 'order_details']));

        $this->assertFalse($preview['applicable']);
        $this->assertFalse($preview['can_post']);
        $this->assertSame(0.0, $preview['total']);
        $this->assertSame([], $preview['lines']);
        $this->assertStringContainsString('بدون تكلفة أصناف', (string) $preview['reason']);
        $this->assertSame(0, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_does_not_auto_post_zero_cost_cogs(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $this->zeroCategoryCost();

        $this->assertSame(
            'skipped_zero',
            app(SalesOrderCogsJournalService::class)->postIfMissing($order->fresh(['order_products', 'order_details']))
        );
        $this->assertSame(0, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_posts_manual_cogs_lines_when_average_cost_is_zero(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $this->zeroCategoryCost();

        $result = app(SalesOrderCogsJournalService::class)->postIfMissingWithLines(
            $order->fresh(['order_products', 'order_details']),
            [
                ['account_id' => (int) $this->cogsAcc->id, 'debit' => 35, 'credit' => 0, 'description' => 'تكلفة يدوية'],
                ['account_id' => (int) $this->inventoryAcc->id, 'debit' => 0, 'credit' => 35, 'description' => 'تكلفة يدوية'],
            ]
        );

        $this->assertSame('posted', $result);
        $this->assertEquals(35.0, (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'COGS-'.$order->id.'-%')
            ->sum('debit'));
    }

    public function test_preview_order_does_not_offer_zero_cogs_when_invoice_exists(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $this->zeroCategoryCost();
        $this->postInvoice($order, 200);

        $preview = app(MissingSalesInvoiceJournalService::class)->previewOrder(
            $this->viewer(),
            (int) $order->id
        );

        $this->assertFalse($preview['can_post']);
        $this->assertStringContainsString('لا توجد قيود ناقصة', (string) $preview['reason']);
        $this->assertNotContains('cogs', array_column($preview['journals'], 'key'));
        $this->assertNotContains('prepaid', array_column($preview['journals'], 'key'));
        $this->assertFalse($preview['cogs']['applicable']);
    }

    public function test_does_not_post_cogs_twice(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $service = app(SalesOrderCogsJournalService::class);

        $this->assertSame('posted', $service->postIfMissing($order));
        $before = AccountEntry::query()->where('order_id', $order->id)->count();
        $this->assertSame('skipped_exists', $service->postIfMissing($order->fresh()));
        $this->assertSame($before, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_skips_non_inventory_order_types(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $order->order_type = 'طلب صيانة';
        $order->save();

        $this->assertSame(
            'skipped_ineligible',
            app(SalesOrderCogsJournalService::class)->postIfMissing($order)
        );
        $this->assertSame(0, AccountEntry::query()->where('order_id', $order->id)->count());
    }

    public function test_prefers_shipment_line_cogs_over_current_average(): void
    {
        $order = $this->makeShippedOrder(2, 50);
        $this->category->update(['total_price' => 9999, 'quantity' => 1, 'unit_price' => 9999]);

        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'shipment_seq' => 1,
            'shipped_at' => '2037-04-20',
            'cogs_total' => 77,
            'lines_total' => 200,
            'is_final' => true,
            'status_after' => 'تم شحن',
        ]);
        OrderShipmentLine::create([
            'order_shipment_id' => $shipment->id,
            'order_product_id' => $order->order_products->first()->id,
            'category_id' => $this->category->id,
            'quantity' => 2,
            'unit_price' => 100,
            'line_total' => 200,
            'unit_cost' => 38.5,
            'line_cogs' => 77,
        ]);

        $result = app(SalesOrderCogsJournalService::class)->postIfMissing($order->fresh('order_products'));
        $this->assertSame('posted', $result);
        $debit = (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'COGS-'.$order->id.'-%')
            ->sum('debit');
        $this->assertEquals(77.0, $debit);
    }

    public function test_lifecycle_posts_missing_cogs_without_touching_existing_invoice(): void
    {
        $order = $this->makeShippedOrder(1, 40);
        $this->postInvoice($order, 200);
        $invoiceCount = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-'.$order->id.'-%')
            ->count();

        $lifecycle = app(SalesOrderLifecycleJournalService::class)->postMissingForOrder($order->fresh(['order_products', 'order_details']));

        $this->assertContains('cogs', $lifecycle['posted']);
        $this->assertSame($invoiceCount, AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-'.$order->id.'-%')
            ->count());
        $this->assertTrue(app(SalesOrderCogsJournalService::class)->hasCogsRecognition($order));
    }

    public function test_missing_preview_includes_orders_with_invoice_but_no_cogs(): void
    {
        $user = $this->viewer();
        $order = $this->makeShippedOrder(1, 40, '2037-04-21');
        $this->postInvoice($order, 200);

        $preview = app(MissingSalesInvoiceJournalService::class)->preview(
            $user,
            '2037-04-21',
            '2037-04-21'
        );

        $this->assertGreaterThanOrEqual(1, $preview['missing_cogs_count']);
        $this->assertContains($order->id, $preview['sample_order_ids']);
        $this->assertSame(0, $preview['missing_invoice_count']);
    }

    public function test_post_missing_creates_cogs_journal_for_shipped_order(): void
    {
        $user = $this->viewer();
        $order = $this->makeShippedOrder(1, 40, '2037-04-22');
        $this->postInvoice($order, 200);

        $result = app(MissingSalesInvoiceJournalService::class)->postMissing(
            $user,
            '2037-04-22',
            '2037-04-22'
        );

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(1, $result['orders_posted']);
        $this->assertTrue(app(SalesOrderCogsJournalService::class)->hasCogsRecognition($order->fresh()));
        $this->assertSame(
            2,
            AccountEntry::query()
                ->where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'ORD-'.$order->id.'-%')
                ->count()
        );
    }

    private function viewer(): User
    {
        $email = 'cogs_'.$this->codePrefix.'@test.local';
        config(['rbac.super_admin_emails' => [$email]]);

        return User::factory()->create([
            'email' => $email,
            'department' => 'COGS-'.$this->codePrefix,
        ]);
    }

    private function seedAccountsAndCategory(): void
    {
        $p = $this->codePrefix;
        $this->cogsAcc = TreeAccount::create([
            'code' => $p.'5001',
            'name' => 'تكلفة مبيعات COGS '.$p,
            'type' => 'expense',
            'level' => 1,
            'parent_id' => null,
            'detail_type' => 'cogs',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $this->inventoryAcc = TreeAccount::create([
            'code' => $p.'1221',
            'name' => 'مخزون منتج تام '.$p,
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'detail_type' => 'inventory_finished',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $productionId = Schema::hasTable('productions') ? (DB::table('productions')->value('id') ?? 1) : 1;
        $measurementId = Schema::hasTable('measurements') ? (DB::table('measurements')->value('id') ?? 1) : 1;

        $this->category = Category::create([
            'category_name' => 'صنف COGS '.$p,
            'category_price' => 80,
            'unit_price' => 50,
            'initial_balance' => 10,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
            'category_image' => '',
            'quantity' => 10,
            'total_price' => 500,
            'sell_total_price' => 0,
        ]);
        DB::table('categories')->where('id', $this->category->id)->update([
            'quantity' => 10,
            'total_price' => 500,
            'unit_price' => 50,
        ]);
        $this->category->refresh();
    }

    private function makeShippedOrder(float $shippedQty, float $unitCostHint, string $orderDate = '2037-04-20'): Order
    {
        $order = Order::withoutEvents(function () use ($orderDate) {
            return Order::create([
                'customer_name' => 'عميل COGS '.$this->codePrefix,
                'customer_type' => 'افراد',
                'customer_phone_1' => '017'.substr(str_replace('.', '', uniqid('', true)), -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => $orderDate,
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 200,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 200,
                'order_status' => 'تم شحن',
            ]);
        });

        OrderProduct::create([
            'order_id' => $order->id,
            'category_id' => $this->category->id,
            'quantity' => $shippedQty,
            'shipped_quantity' => $shippedQty,
            'price' => 100,
            'total_price' => $shippedQty * 100,
        ]);

        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_date' => $orderDate,
        ]);

        $this->assertGreaterThan(0, $unitCostHint);

        return $order->fresh(['order_products', 'order_details']);
    }

    private function zeroCategoryCost(): void
    {
        DB::table('categories')->where('id', $this->category->id)->update([
            'quantity' => 0,
            'total_price' => 0,
            'unit_price' => 0,
        ]);
        $this->category->refresh();
        if (Schema::hasTable('warehouse_ratings')) {
            DB::table('warehouse_ratings')->where('category_id', $this->category->id)->delete();
        }
    }

    private function postInvoice(Order $order, float $amount): void
    {
        $batch = 'ORD-'.$order->id.'-20260901120000';
        $customer = TreeAccount::create([
            'code' => $this->codePrefix.'C'.substr((string) $order->id, -4),
            'name' => 'عميل فاتورة '.$order->id,
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        AccountEntry::create([
            'tree_account_id' => $customer->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'فاتورة مبيعات — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
        AccountEntry::create([
            'tree_account_id' => $customer->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'فاتورة مبيعات — طلب رقم '.$order->id,
            'order_id' => $order->id,
            'entry_batch_code' => $batch,
        ]);
    }
}
