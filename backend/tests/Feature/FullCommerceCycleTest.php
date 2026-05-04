<?php

namespace Tests\Feature;

use App\Enums\ProductionOrderStatus;
use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Category;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\Production;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\ShippingCompany;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Models\User;
use App\Models\shippingCompanyDetails;
use App\Models\shippingline;
use App\Services\Manufacturing\ProductionOrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * دورة تكاملية: صنف خام + تصنيع (أمر إنتاج) + صنف تام → طلب HTTP → تأكيد → شحن
 * (شركة شحن + شركة تحصيل مع دفعة مقدمة) → تحصيل.
 *
 * يعتمد على قاعدة البيانات الحقيقية (مثل باقي Feature tests) مع rollback بعد كل اختبار.
 */
class FullCommerceCycleTest extends TestCase
{
    private User $user;

    private Production $production;

    private Measurement $measurement;

    private Stock $rawStock;

    private Stock $finishedStock;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create([
                'department' => 'Admin',
                'email' => 'e2e_' . uniqid() . '@test.local',
            ]);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'خط E2E',
        ]);

        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->rawStock = Stock::where('name', 'مخزن مواد خام')->first()
            ?? Stock::create(['name' => 'مخزن مواد خام', 'balance' => 0, 'asset_id' => 0]);

        $this->finishedStock = Stock::where('name', 'مخزن منتج تام')->first()
            ?? Stock::create(['name' => 'مخزن منتج تام', 'balance' => 0, 'asset_id' => 0]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_raw_material_production_order_sales_ship_collect_with_receivable_split(): void
    {
        if (! Schema::hasColumn('categories', 'product_type')) {
            $this->markTestSkipped('Migration not applied: categories.product_type');
        }

        $shippingMethodId = DB::table('shipping_methods')->value('id');
        $orderSourceId = DB::table('order_sources')->value('id');
        if (! $shippingMethodId || ! $orderSourceId) {
            $this->markTestSkipped('Need at least one shipping_methods and order_sources row.');
        }

        $line = shippingline::query()->first() ?? shippingline::create(['name' => 'خط E2E ' . uniqid()]);

        $bank = Bank::query()->whereNotNull('asset_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Need a Bank with asset_id for prepaid on order store.');
        }

        $prefix = 'E2E' . substr(str_replace('.', '', uniqid('', true)), -8);

        $collectAr = TreeAccount::create([
            'code' => $prefix . 'COL',
            'name' => 'ذمة تحصيل E2E',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $shipAr = TreeAccount::create([
            'code' => $prefix . 'SHP',
            'name' => 'ذمة شحن E2E',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $paymob = ShippingCompany::create([
            'name' => 'Paymob E2E ' . $prefix,
            'type' => 'شركة',
            'receivable_tree_account_id' => $collectAr->id,
        ]);

        $bosta = ShippingCompany::create([
            'name' => 'Bosta E2E ' . $prefix,
            'type' => 'شركة',
            'receivable_tree_account_id' => $shipAr->id,
        ]);

        $raw = Category::create([
            'category_name' => 'RM_E2E_' . $prefix,
            'category_price' => 5,
            'unit_price' => 5,
            'initial_balance' => 100,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->rawStock->id,
            'category_image' => '',
            'product_type' => 'raw_material',
        ]);
        $raw->quantity = 100;
        $raw->total_price = 500;
        $raw->save();

        $fg = Category::create([
            'category_name' => 'FG_E2E_' . $prefix,
            'category_price' => 40,
            'unit_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
            'quantity' => 0,
            'sell_total_price' => 0,
            'product_type' => 'finished',
        ]);

        $recipe = Recipe::create([
            'recipe_name' => 'R_E2E_' . $prefix,
            'output_item_id' => $fg->id,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id' => $raw->id,
            'quantity' => 2,
            'unit_cost' => null,
        ]);
        $fg->recipe_id = $recipe->id;
        $fg->save();

        /** @var ProductionOrderLifecycleService $lifecycle */
        $lifecycle = app(ProductionOrderLifecycleService::class);
        $po = $lifecycle->createDraft((int) $recipe->id, (int) $fg->id, '3', 'e2e', $this->user->id);
        $lifecycle->start($po, $this->user->id);
        $lifecycle->complete(ProductionOrder::findOrFail($po->id), $this->user->id);

        $fg->refresh();
        $this->assertSame(ProductionOrderStatus::Completed, ProductionOrder::find($po->id)->status);
        $this->assertEqualsWithDelta(3.0, (float) $fg->quantity, 0.0001);

        $qty = 2;
        $linePrice = 50.0;
        $productTotal = $qty * $linePrice;
        $shippingRevenue = 30.0;
        $prepaid = 40.0;
        $discount = 0.0;
        $totalInvoice = $productTotal + $shippingRevenue;
        $netTotal = $totalInvoice - $prepaid - $discount;

        $phone = '010' . substr((string) random_int(10000000, 99999999), 0, 8);

        $orderDetailsPayload = [[
            'category_id' => $fg->id,
            'quantity' => $qty,
            'price' => $linePrice,
            'total' => $productTotal,
            'special_details' => null,
        ]];

        $store = $this->actingAs($this->user, 'api')->postJson('/api/orders', [
            'customer_name' => 'عميل E2E',
            'customer_type' => 'افراد',
            'customer_phone_1' => $phone,
            'customer_phone_2' => '-',
            'tel' => '-',
            'governorate' => 'القاهرة',
            'city' => 'القاهرة',
            'address' => 'عنوان اختبار',
            'order_date' => now()->toDateString(),
            'shipping_method_id' => $shippingMethodId,
            'order_source_id' => $orderSourceId,
            'order_type' => 'جديد',
            'shipping_cost' => $shippingRevenue,
            'total_invoice' => $totalInvoice,
            'prepaid_amount' => $prepaid,
            'discount' => $discount,
            'net_total' => $netTotal,
            'bank' => $bank->id,
            'vat' => 0,
            'order_details' => json_encode($orderDetailsPayload),
        ]);

        $store->assertCreated();
        $orderId = (int) Order::where('customer_phone_1', $phone)->latest('id')->value('id');
        $this->assertGreaterThan(0, $orderId);

        $op = OrderProduct::where('order_id', $orderId)->firstOrFail();

        $confirm = $this->actingAs($this->user, 'api')->postJson("/api/confirm/{$orderId}", [
            'date' => now()->toDateString(),
            'line_id' => $line->id,
        ]);
        $confirm->assertStatus(201);

        $shipPayload = [
            'date' => now()->toDateString(),
            'company_id' => $bosta->id,
            'collection_company_id' => $paymob->id,
            'productsToShip' => json_encode([['id' => $op->id, 'quantity' => $qty]]),
            'shippment_number' => 'E2E-' . $prefix,
        ];

        $ship = $this->actingAs($this->user, 'api')->post("/api/shiporder/{$orderId}", $shipPayload);
        $ship->assertStatus(200);

        $order = Order::findOrFail($orderId);
        $this->assertSame('تم شحن', $order->order_status);

        $splitCollect = min($prepaid, $netTotal);
        $splitShip = max(0.0, round($netTotal - $splitCollect, 3));

        $shipRows = shippingCompanyDetails::where('order_id', $orderId)->where('status', 'تم شحن')->get();
        $this->assertCount(2, $shipRows, 'Expected one receivable row per party (Bosta + Paymob).');

        if (Schema::hasColumn('order_details', 'shipping_receivable_amount')) {
            $od = OrderDetails::where('order_id', $orderId)->first();
            $this->assertNotNull($od);
            $this->assertEqualsWithDelta($splitShip, (float) $od->shipping_receivable_amount, 0.02);
            $this->assertEqualsWithDelta($splitCollect, (float) $od->collection_receivable_amount, 0.02);
        }

        $bosta->refresh();
        $paymob->refresh();
        $this->assertEqualsWithDelta($splitShip, (float) $bosta->balance, 0.02, 'Bosta operational balance = COD part');
        $this->assertEqualsWithDelta($splitCollect, (float) $paymob->balance, 0.02, 'Paymob operational balance = prepaid part');

        $collect = $this->actingAs($this->user, 'api')->postJson("/api/collectorder/{$orderId}", [
            'payment_type' => 'bank',
            'bank_id' => $bank->id,
        ]);
        $collect->assertOk();

        $order->refresh();
        $this->assertSame('تم التحصيل', $order->order_status);

        $entries = AccountEntry::where('order_id', $orderId)->get();
        if ($entries->isNotEmpty()) {
            $d = round((float) $entries->sum('debit'), 2);
            $c = round((float) $entries->sum('credit'), 2);
            $this->assertEquals($d, $c, 'GL lines for order_id must balance');
        }
    }
}
