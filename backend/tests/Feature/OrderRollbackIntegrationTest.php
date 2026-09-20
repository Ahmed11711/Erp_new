<?php

namespace Tests\Feature;

use App\Enums\OrderRollbackTarget;
use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderRollbackAudit;
use App\Models\OrderStatusHistory;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Models\User;
use App\Models\shippingCompanyDetails;
use App\Models\shippingline;
use App\Services\Accounting\DeliveryConfirmationAccountingService;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\Orders\OrderRollbackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * اختبار تكاملي كامل لإعادة فتح الطلب:
 * إنشاء → تأكيد → شحن → تسليم → (تحصيل) → rollback
 * مع التحقق من المحاسبة والمخزون والصلاحيات وسجل التدقيق.
 */
class OrderRollbackIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;

    private string $prefix = '';

    private ?Bank $bank = null;

    private ?ShippingCompany $shippingCo = null;

    private ?TreeAccount $customerAcc = null;

    private ?TreeAccount $shippingReceivableAcc = null;

    private ?Category $product = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix = 'RB' . substr(str_replace('.', '', uniqid('', true)), -8);

        $this->adminUser = User::where('department', 'Admin')->first()
            ?? User::query()->firstOrFail();

        $this->seedMinimalAccounting();
        $this->seedProductWithStock();
    }

    private function seedMinimalAccounting(): void
    {
        $p = $this->prefix;

        $root = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'أصول RB',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $customersParent = TreeAccount::create([
            'code' => $p . '1100',
            'name' => 'عملاء RB',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->customerAcc = TreeAccount::create([
            'code' => $p . '1100001',
            'name' => 'عميل RB',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $customersParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $cashParent = TreeAccount::create([
            'code' => $p . '1000200',
            'name' => 'نقدية RB',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $bankAcc = TreeAccount::create([
            'code' => $p . '1000211',
            'name' => 'بنك RB',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $cashParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $revenueRoot = TreeAccount::create([
            'code' => $p . '4000',
            'name' => 'إيرادات RB',
            'type' => 'revenue',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        TreeAccount::create([
            'code' => $p . '4011001',
            'name' => 'مبيعات RB',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'sales',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        TreeAccount::create([
            'code' => $p . '4031001',
            'name' => 'شحن RB',
            'type' => 'revenue',
            'level' => 4,
            'parent_id' => $revenueRoot->id,
            'detail_type' => 'shipping_revenue',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->shippingReceivableAcc = TreeAccount::create([
            'code' => $p . '1100500',
            'name' => 'ذمة شحن RB',
            'type' => 'asset',
            'level' => 4,
            'parent_id' => $customersParent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->bank = Bank::create([
            'name' => 'بنك RB ' . $p,
            'type' => 'بنك',
            'usage' => 'test',
            'balance' => 50000,
            'asset_id' => $bankAcc->id,
        ]);

        $this->shippingCo = ShippingCompany::create([
            'name' => 'شركة RB ' . $p,
            'type' => 'شركة',
            'balance' => 0,
            'receivable_tree_account_id' => $this->shippingReceivableAcc->id,
        ]);
    }

    private function seedProductWithStock(): void
    {
        $productionId = DB::table('productions')->value('id') ?? 1;
        $measurementId = DB::table('measurements')->value('id') ?? 1;

        $this->product = Category::create([
            'category_name' => 'Prod_RB_' . $this->prefix,
            'category_price' => 100,
            'unit_price' => 50,
            'initial_balance' => 20,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
            'category_image' => '',
            'total_price' => 1000,
            'sell_total_price' => 0,
        ]);
        DB::table('categories')->where('id', $this->product->id)->update([
            'quantity' => 20,
            'total_price' => 1000,
        ]);
        $this->product->refresh();
    }

    /**
     * @return array{order: Order, order_product: OrderProduct}
     */
    private function createConfirmedOrderReadyToShip(float $netTotal = 500.0): array
    {
        $shippingMethodId = DB::table('shipping_methods')->value('id');
        $orderSourceId = DB::table('order_sources')->value('id');
        if (! $shippingMethodId || ! $orderSourceId) {
            $this->markTestSkipped('Need shipping_methods and order_sources rows.');
        }

        $phone = '010' . substr((string) random_int(10000000, 99999999), 0, 8);
        $qty = 2;
        $linePrice = 250.0;

        $store = $this->actingAs($this->adminUser, 'api')->postJson('/api/orders', [
            'customer_name' => 'عميل RB ' . $this->prefix,
            'customer_type' => 'افراد',
            'customer_phone_1' => $phone,
            'customer_phone_2' => '-',
            'tel' => '-',
            'governorate' => 'القاهرة',
            'city' => 'القاهرة',
            'address' => 'عنوان RB',
            'order_date' => now()->toDateString(),
            'shipping_method_id' => $shippingMethodId,
            'order_source_id' => $orderSourceId,
            'order_type' => 'جديد',
            'shipping_cost' => 0,
            'total_invoice' => $netTotal,
            'prepaid_amount' => 0,
            'discount' => 0,
            'net_total' => $netTotal,
            'bank' => $this->bank?->id,
            'vat' => 0,
            'order_details' => json_encode([[
                'category_id' => $this->product?->id,
                'quantity' => $qty,
                'price' => $linePrice,
                'total' => $qty * $linePrice,
                'special_details' => null,
            ]]),
        ]);

        $store->assertCreated();

        $order = Order::where('customer_phone_1', $phone)->latest('id')->firstOrFail();
        $op = OrderProduct::where('order_id', $order->id)->firstOrFail();

        $line = shippingline::query()->first() ?? shippingline::create(['name' => 'خط RB ' . $this->prefix]);

        $confirm = $this->actingAs($this->adminUser, 'api')->postJson("/api/confirm/{$order->id}", [
            'date' => now()->toDateString(),
            'line_id' => $line->id,
        ]);
        $confirm->assertStatus(201);

        $order->refresh();
        $this->assertSame('طلب مؤكد', $order->order_status);

        return ['order' => $order, 'order_product' => $op];
    }

    private function shipAndDeliverOrder(Order $order, OrderProduct $op): void
    {
        $qtyBefore = (float) Category::find($op->category_id)?->quantity;

        $ship = $this->actingAs($this->adminUser, 'api')->post("/api/shiporder/{$order->id}", [
            'date' => now()->toDateString(),
            'company_id' => $this->shippingCo?->id,
            'productsToShip' => json_encode([['id' => $op->id, 'quantity' => (float) $op->quantity]]),
        ]);
        $ship->assertStatus(200);

        $order->refresh();
        $this->assertSame('تم شحن', $order->order_status);

        $op->refresh();
        $this->assertEquals((float) $op->quantity, (float) $op->shipped_quantity);

        $this->product?->refresh();
        $this->assertEqualsWithDelta(
            $qtyBefore - (float) $op->quantity,
            (float) Category::find($op->category_id)?->quantity,
            0.01,
            'Inventory should decrease on ship'
        );

        $this->assertTrue(
            shippingCompanyDetails::where('order_id', $order->id)->where('status', 'تم شحن')->exists(),
            'Shipping company detail row should exist after ship'
        );

        $deliver = $this->actingAs($this->adminUser, 'api')->postJson("/api/order/{$order->id}/deliver");
        $deliver->assertOk();

        $order->refresh();
        $this->assertSame('تم التسليم', $order->order_status);

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertNotNull($od?->delivery_batch_code);
        $this->assertNotNull($od?->delivery_date);

        $this->assertTrue(
            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'DELIVERY-' . $order->id . '-%')
                ->exists(),
            'Delivery GL entries should exist'
        );
    }

    public function test_http_full_cycle_ship_deliver_rollback_to_confirmed(): void
    {
        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(500.0);
        $this->shipAndDeliverOrder($order, $op);

        $deliveryEntryCount = AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-' . $order->id . '-%')
            ->count();

        $preview = $this->actingAs($this->adminUser, 'api')
            ->getJson("/api/orders/{$order->id}/rollback/preview?target=confirmed");
        $preview->assertOk()
            ->assertJsonPath('current_status', 'تم التسليم')
            ->assertJsonPath('target_status', 'طلب مؤكد')
            ->assertJsonStructure(['operations']);

        $this->assertNotEmpty($preview->json('operations'));

        $rollback = $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'confirmed',
            'reason' => 'اختبار تكاملي — إعادة فتح بعد التسليم',
            'confirmed' => true,
        ]);
        $rollback->assertOk()
            ->assertJsonPath('new_status', 'طلب مؤكد')
            ->assertJsonPath('previous_status', 'تم التسليم');

        $order->refresh();
        $this->assertSame('طلب مؤكد', $order->order_status);

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertNull($od?->delivery_date);
        $this->assertNull($od?->delivery_batch_code);
        $this->assertNull($od?->shipping_date);
        $this->assertNotNull($od?->need_by_date, 'Need-by date should remain when rolling back to confirmed');

        $op->refresh();
        $this->assertEquals(0.0, (float) $op->shipped_quantity, 'Shipped qty should reset');

        $category = Category::find($op->category_id);
        $this->assertNotNull($category);
        $this->assertEqualsWithDelta(20.0, (float) $category->quantity, 0.01, 'Inventory should be restored');

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'old_status' => 'تم التسليم',
            'new_status' => 'طلب مؤكد',
        ]);

        $audit = OrderRollbackAudit::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame('اختبار تكاملي — إعادة فتح بعد التسليم', $audit->reason);

        $this->assertEquals(
            $deliveryEntryCount,
            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'DELIVERY-' . $order->id . '-%')
                ->count(),
            'Original DELIVERY entries must NOT be deleted'
        );

        $this->assertTrue(
            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', 'ROLLBACK-%')
                ->exists(),
            'Mirror ROLLBACK entries should be created'
        );

        $this->assertTrue(
            shippingCompanyDetails::where('order_id', $order->id)
                ->where('status', 'إعادة فتح')
                ->exists(),
            'Shipping detail should be marked reopened'
        );

        $this->shippingCo?->refresh();
        $this->assertEqualsWithDelta(
            0.0,
            (float) $this->shippingCo?->balance,
            0.02,
            'Shipping company operational balance should return to zero after rollback'
        );

        $allEntries = AccountEntry::where('order_id', $order->id)->get();
        if ($allEntries->isNotEmpty()) {
            $d = round((float) $allEntries->sum('debit'), 2);
            $c = round((float) $allEntries->sum('credit'), 2);
            $this->assertEquals($d, $c, 'All GL lines for order must balance after rollback');
        }
    }

    public function test_delivered_order_rollback_clears_shipping_company_receivable_balance(): void
    {
        $balanceBefore = (float) $this->shippingCo?->refresh()->balance;

        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(450.0);
        $this->shipAndDeliverOrder($order, $op);

        $this->shippingCo?->refresh();
        $this->assertEqualsWithDelta(
            $balanceBefore + 450.0,
            (float) $this->shippingCo?->balance,
            0.02,
            'Shipping company balance should increase after ship (unchanged on deliver)'
        );

        $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'confirmed',
            'reason' => 'اختبار عكس مديونية شركة الشحن',
            'confirmed' => true,
        ])->assertOk();

        $this->shippingCo?->refresh();
        $this->assertEqualsWithDelta(
            $balanceBefore,
            (float) $this->shippingCo?->balance,
            0.02,
            'Rollback must reverse shipping company operational receivable'
        );
    }

    public function test_collect_order_reduces_shipping_company_operational_balance(): void
    {
        $balanceBefore = (float) $this->shippingCo?->refresh()->balance;

        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(350.0);
        $this->shipAndDeliverOrder($order, $op);

        $this->shippingCo?->refresh();
        $this->assertEqualsWithDelta(
            $balanceBefore + 350.0,
            (float) $this->shippingCo?->balance,
            0.02,
            'Balance should increase after ship'
        );
        $this->assertEqualsWithDelta(
            $balanceBefore + 350.0,
            $this->shippingCo?->displayBalance(),
            0.02,
            'displayBalance should reflect operational COD after ship'
        );

        $this->actingAs($this->adminUser, 'api')->postJson("/api/collectorder/{$order->id}", [
            'payment_type' => 'bank',
            'bank_id' => $this->bank?->id,
        ])->assertOk();

        $this->shippingCo?->refresh();
        $this->assertEqualsWithDelta(
            $balanceBefore,
            (float) $this->shippingCo?->balance,
            0.02,
            'Operational balance must decrease by collected amount'
        );
        $this->assertEqualsWithDelta(
            $balanceBefore,
            $this->shippingCo?->displayBalance(),
            0.02,
            'displayBalance must decrease after collect'
        );
    }

    public function test_collected_order_rollback_reverses_collection_and_preserves_gl(): void
    {
        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(300.0);
        $this->shipAndDeliverOrder($order, $op);

        $collect = $this->actingAs($this->adminUser, 'api')->postJson("/api/collectorder/{$order->id}", [
            'payment_type' => 'bank',
            'bank_id' => $this->bank?->id,
        ]);
        $collect->assertOk();

        $order->refresh();
        $this->assertSame('تم التحصيل', $order->order_status);

        $entriesBeforeRollback = AccountEntry::where('order_id', $order->id)->pluck('id')->all();
        $this->assertNotEmpty($entriesBeforeRollback, 'Order should have GL entries after collect');

        $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'confirmed',
            'reason' => 'اختبار عكس التحصيل',
            'confirmed' => true,
        ])->assertOk();

        $order->refresh();
        $this->assertSame('طلب مؤكد', $order->order_status);

        $entriesAfterRollback = AccountEntry::where('order_id', $order->id)->pluck('id')->all();
        foreach ($entriesBeforeRollback as $entryId) {
            $this->assertContains($entryId, $entriesAfterRollback, 'Original GL row must not be deleted');
        }
        $this->assertGreaterThan(
            count($entriesBeforeRollback),
            count($entriesAfterRollback),
            'Rollback should append mirror GL entries'
        );

        $this->assertTrue(
            shippingCompanyDetails::where('order_id', $order->id)
                ->where('status', 'إعادة فتح')
                ->exists()
        );
    }

    public function test_rollback_to_new_clears_confirm_date_for_admin(): void
    {
        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(200.0);
        $this->shipAndDeliverOrder($order, $op);

        $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'new',
            'reason' => 'إعادة إلى جديد — admin',
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('new_status', 'طلب جديد');

        $order->refresh();
        $this->assertSame('طلب جديد', $order->order_status);

        $od = OrderDetails::where('order_id', $order->id)->first();
        $this->assertNull($od?->confirm_date, 'Confirm date should be cleared when rolling back to new');
    }

    public function test_non_admin_can_rollback_to_new_and_notifies_admin(): void
    {
        $nonAdmin = User::where('department', '!=', 'Admin')
            ->where('department', '!=', 'admin')
            ->whereNotNull('department')
            ->first();

        $admin = User::whereIn('department', ['Admin', 'admin'])->first();

        if (! $nonAdmin || ! $admin) {
            $this->markTestSkipped('Need non-admin and admin users in database.');
        }

        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(150.0);
        $this->shipAndDeliverOrder($order, $op);

        $this->actingAs($nonAdmin, 'api')->getJson("/api/orders/{$order->id}/rollback/preview?target=new")
            ->assertOk()
            ->assertJsonPath('target_status', 'طلب جديد');

        $this->actingAs($nonAdmin, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'new',
            'reason' => 'إعادة فتح من مستخدم تشغيل',
            'confirmed' => true,
        ])->assertOk()->assertJsonPath('new_status', 'طلب جديد');

        $order->refresh();
        $this->assertSame('طلب جديد', $order->order_status);

        $this->assertDatabaseHas('notifications', [
            'send_from' => $nonAdmin->id,
            'send_to' => $admin->id,
            'order_id' => $order->id,
            'type' => 'إعادة فتح طلب',
        ]);
    }

    public function test_execute_rejects_missing_reason_and_without_confirmation(): void
    {
        ['order' => $order, 'order_product' => $op] = $this->createConfirmedOrderReadyToShip(100.0);
        $this->shipAndDeliverOrder($order, $op);

        $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'confirmed',
            'reason' => '',
            'confirmed' => true,
        ])->assertStatus(422);

        $this->actingAs($this->adminUser, 'api')->postJson("/api/orders/{$order->id}/rollback", [
            'target' => 'confirmed',
            'reason' => 'سبب صحيح',
            'confirmed' => false,
        ])->assertStatus(422);

        $order->refresh();
        $this->assertSame('تم التسليم', $order->order_status);
    }

    public function test_service_rollback_from_received_maintenance_status(): void
    {
        $order = Order::withoutEvents(fn () => Order::create([
            'customer_name' => 'صيانة RB',
            'customer_type' => 'فرد',
            'customer_phone_1' => '01055554444',
            'customer_phone_2' => '',
            'governorate' => 'القاهرة',
            'city' => 'القاهرة',
            'address' => 'test',
            'order_date' => now()->toDateString(),
            'shipping_method_id' => 1,
            'order_source_id' => 1,
            'order_type' => 'طلب صيانة',
            'total_invoice' => 100,
            'net_total' => 100,
            'order_status' => 'تم الاستلام',
        ]));

        OrderDetails::create([
            'order_id' => $order->id,
            'confirm_date' => now()->toDateString(),
            'receiving_date' => now()->toDateString(),
        ]);

        $svc = app(OrderRollbackService::class);
        $this->assertTrue($svc->isEligible($order));

        $result = $svc->execute(
            $order,
            OrderRollbackTarget::Confirmed,
            'اختبار استلام صيانة',
            (int) $this->adminUser->id,
        );

        $this->assertSame('طلب مؤكد', $result['new_status']);
        $this->assertTrue(
            OrderStatusHistory::where('order_id', $order->id)
                ->where('old_status', 'تم الاستلام')
                ->exists()
        );
    }

    public function test_delivery_gl_reversal_is_mirror_not_delete(): void
    {
        $order = Order::withoutEvents(function () {
            $o = Order::create([
                'customer_name' => 'GL RB ' . $this->prefix,
                'customer_type' => 'فرد',
                'customer_phone_1' => '010' . substr($this->prefix, -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'test',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'total_invoice' => 400,
                'net_total' => 400,
                'order_status' => 'تم التسليم',
            ]);

            OrderDetails::create([
                'order_id' => $o->id,
                'confirm_date' => now()->toDateString(),
                'shipping_company_id' => $this->shippingCo?->id,
                'shipping_receivable_amount' => 400,
                'collection_receivable_amount' => 0,
            ]);

            return $o;
        });

        app(SalesOrderAccountingService::class)->recordInitialOrderRecognition($order->fresh(['order_products']));

        $deliverySvc = app(DeliveryConfirmationAccountingService::class);
        $deliverySvc->recordDeliveryReceivableTransfer($order->fresh(['order_details']));

        $originalIds = AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-' . $order->id . '-%')
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($originalIds);

        app(OrderRollbackService::class)->execute(
            $order,
            OrderRollbackTarget::Confirmed,
            'اختبار مرآة GL',
            (int) $this->adminUser->id,
        );

        foreach ($originalIds as $id) {
            $this->assertNotNull(
                AccountEntry::find($id),
                'Original entry id=' . $id . ' must still exist'
            );
        }

        $rollbackEntries = AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ROLLBACK-%')
            ->get();

        $this->assertNotEmpty($rollbackEntries);

        $originalSumDr = AccountEntry::whereIn('id', $originalIds)->sum('debit');
        $originalSumCr = AccountEntry::whereIn('id', $originalIds)->sum('credit');
        $rollbackSumDr = $rollbackEntries->sum('debit');
        $rollbackSumCr = $rollbackEntries->sum('credit');

        $this->assertEqualsWithDelta((float) $originalSumDr, (float) $rollbackSumCr, 0.02);
        $this->assertEqualsWithDelta((float) $originalSumCr, (float) $rollbackSumDr, 0.02);
    }
}
