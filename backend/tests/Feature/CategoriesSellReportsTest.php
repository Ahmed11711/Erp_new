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

/**
 * تقرير مبيعات الأصناف + باراميتر البحث بالاسم.
 */
class CategoriesSellReportsTest extends TestCase
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
            'production_line' => 'خط تقرير الأصناف',
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

    public function test_categories_sell_reports_requires_authentication(): void
    {
        $response = $this->getJson('/api/reports/categoriesSellReports?itemsPerPage=10&page=1&date_from=2026-01-01&date_to=2026-01-31&sort=total_new');

        $response->assertStatus(401);
    }

    public function test_categories_sell_reports_returns_json_structure(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            '/api/reports/categoriesSellReports?itemsPerPage=10&page=1&date_from=2026-01-01&date_to=2026-01-31&sort=total_new'
        );

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'total',
            'per_page',
        ]);
        $json = $response->json();
        $this->assertArrayHasKey('profitability_totals', $json);
    }

    public function test_search_filters_categories_by_name(): void
    {
        $shippingId = ShippingMethod::query()->value('id');
        $sourceId = OrderSource::query()->value('id');
        if (! $shippingId || ! $sourceId) {
            $this->markTestSkipped('تحتاج جدول shipping_methods أو order_sources يحتوي صفاً واحداً على الأقل.');
        }

        $unique = 'RepSearch_' . uniqid();
        $category = Category::create([
            'category_name' => $unique . ' منتج تجريبي',
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

        $orderDate = '2026-04-28';
        $order = Order::create([
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
            'order_status' => 'تم التسليم',
            'order_type' => 'جديد',
            'shipping_cost' => 0,
            'total_invoice' => 100,
            'prepaid_amount' => 0,
            'discount' => 0,
            'net_total' => 100,
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'quantity' => '2',
            'price' => 50,
            'total_price' => 100,
        ]);

        $baseQuery = 'itemsPerPage=50&page=1&date_from=' . $orderDate . '&date_to=' . $orderDate . '&sort=category_name';

        $withSearch = $this->actingAs($this->user)->getJson(
            '/api/reports/categoriesSellReports?' . $baseQuery . '&search=' . rawurlencode($unique)
        );
        $withSearch->assertOk();
        $names = collect($withSearch->json('data'))->pluck('category_name')->all();
        $this->assertNotEmpty($names);
        $this->assertTrue(
            collect($names)->contains(fn ($n) => str_contains((string) $n, $unique)),
            'Expected category name containing search token.'
        );

        $noMatch = $this->actingAs($this->user)->getJson(
            '/api/reports/categoriesSellReports?' . $baseQuery . '&search=' . rawurlencode($unique . '_no_such_row_xyz')
        );
        $noMatch->assertOk();
        $this->assertSame(0, (int) $noMatch->json('total'));
        $this->assertSame([], $noMatch->json('data'));
    }
}
