<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\Production;
use App\Models\ShippingMethod;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoriesStatusReportsTest extends TestCase
{
    private User $user;

    private Production $production;

    private Measurement $measurement;

    private Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن منتج تام',
            'production_line' => 'خط تقرير حالة الأصناف',
        ]);

        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن منتج تام',
        ]);

        $this->stock = Stock::where('name', 'مخزن منتج تام')->first()
            ?? Stock::create(['name' => 'مخزن منتج تام', 'balance' => 0, 'asset_id' => 0]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_categories_status_reports_requires_authentication(): void
    {
        $response = $this->getJson('/api/reports/categoriesStatusReports?itemsPerPage=10&page=1&date_from=2026-01-01&date_to=2026-01-31');

        $response->assertStatus(401);
    }

    public function test_categories_status_reports_filters_by_order_status_and_returns_item_fields(): void
    {
        $shippingId = ShippingMethod::query()->value('id');
        $sourceId = OrderSource::query()->value('id');
        if (! $shippingId || ! $sourceId) {
            $this->markTestSkipped('تحتاج جدول shipping_methods أو order_sources يحتوي صفاً واحداً على الأقل.');
        }

        $category = Category::create([
            'category_name' => 'Stam Status Report '.uniqid(),
            'item_code' => 'STM-001',
            'color' => 'Beige',
            'product_type' => 'finished',
            'category_price' => 100,
            'unit_price' => 100,
            'initial_balance' => 10,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->stock->id,
            'category_image' => '',
        ]);
        $category->quantity = 10;
        $category->total_price = 1000;
        $category->save();

        $orderDate = '2026-07-26';

        $confirmed = Order::create([
            'customer_name' => 'اختبار',
            'customer_type' => 'فرد',
            'customer_phone_1' => '01000000000',
            'customer_phone_2' => '',
            'governorate' => 'القاهرة',
            'city' => null,
            'address' => 'عنوان',
            'order_date' => $orderDate,
            'shipping_method_id' => $shippingId,
            'order_source_id' => $sourceId,
            'order_status' => 'طلب مؤكد',
            'order_type' => 'جديد',
            'shipping_cost' => 0,
            'total_invoice' => 200,
            'prepaid_amount' => 0,
            'discount' => 0,
            'net_total' => 200,
        ]);

        $delivered = Order::create([
            'customer_name' => 'اختبار',
            'customer_type' => 'فرد',
            'customer_phone_1' => '01000000001',
            'customer_phone_2' => '',
            'governorate' => 'القاهرة',
            'city' => null,
            'address' => 'عنوان',
            'order_date' => $orderDate,
            'shipping_method_id' => $shippingId,
            'order_source_id' => $sourceId,
            'order_status' => 'تم التسليم',
            'order_type' => 'جديد',
            'shipping_cost' => 0,
            'total_invoice' => 100,
            'prepaid_amount' => 0,
            'discount' => 0,
            'net_total' => 100,
        ]);

        OrderProduct::create([
            'order_id' => $confirmed->id,
            'category_id' => $category->id,
            'quantity' => '2',
            'price' => 100,
            'total_price' => 200,
        ]);

        OrderProduct::create([
            'order_id' => $delivered->id,
            'category_id' => $category->id,
            'quantity' => '1',
            'price' => 100,
            'total_price' => 100,
        ]);

        $base = 'itemsPerPage=50&page=1&date_from='.$orderDate.'&date_to='.$orderDate.'&sort=total_quantity_new';

        $confirmedOnly = $this->actingAs($this->user)->getJson(
            '/api/reports/categoriesStatusReports?'.$base.'&order_statuses='.rawurlencode('طلب مؤكد')
        );

        $confirmedOnly->assertOk();
        $row = collect($confirmedOnly->json('data'))->firstWhere('category_id', $category->id);
        $this->assertNotNull($row);
        $this->assertSame('Beige', $row['color']);
        $this->assertSame('STM-001', $row['item_code']);
        $this->assertSame('منتج تام', $row['product_type_label']);
        $this->assertEquals(2, (int) $row['total_quantity_new']);

        $both = $this->actingAs($this->user)->getJson(
            '/api/reports/categoriesStatusReports?'.$base.'&order_statuses='.rawurlencode('طلب مؤكد,تم التسليم')
        );

        $both->assertOk();
        $rowBoth = collect($both->json('data'))->firstWhere('category_id', $category->id);
        $this->assertNotNull($rowBoth);
        $this->assertEquals(3, (int) $rowBoth['total_quantity_new']);
    }
}
