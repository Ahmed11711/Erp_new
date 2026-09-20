<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderSource;
use App\Models\User;
use App\Services\Orders\OrderLineCancellationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderLineCancellationTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'OLC'.substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_cancel_line_recalculates_totals(): void
    {
        $order = $this->createOrderWithProducts([
            ['category_id' => 1, 'quantity' => 3, 'price' => 100.0],
            ['category_id' => 2, 'quantity' => 1, 'price' => 50.0],
        ], [
            'total_invoice' => 400.0,
            'discount' => 0,
            'prepaid_amount' => 0,
            'net_total' => 400.0,
        ]);

        $line = OrderProduct::query()->where('order_id', $order->id)->where('category_id', 1)->firstOrFail();

        $service = app(OrderLineCancellationService::class);
        $result = $service->cancelLines($order, [[
            'order_product_id' => $line->id,
            'quantity' => 1,
            'reason' => 'نفاد المخز',
        ]], 1);

        $this->assertFalse($result['full_cancelled']);
        $this->assertEqualsWithDelta(100.0, $result['cancelled_amount'], 0.01);

        $line->refresh();
        $this->assertEqualsWithDelta(1.0, (float) $line->cancelled_quantity, 0.001);
        $this->assertStringContainsString('نفاد المخز', (string) $line->cancellation_reason);
        $this->assertEqualsWithDelta(200.0, (float) $line->total_price, 0.01);

        $order->refresh();
        $this->assertEqualsWithDelta(300.0, (float) $order->total_invoice, 0.01);
        $this->assertEqualsWithDelta(300.0, (float) $order->net_total, 0.01);
        $this->assertSame('طلب مؤكد', $order->order_status);
    }

    public function test_cannot_cancel_more_than_remaining(): void
    {
        $order = $this->createOrderWithProducts([
            ['category_id' => 1, 'quantity' => 2, 'price' => 100.0],
        ]);
        $line = OrderProduct::query()->where('order_id', $order->id)->firstOrFail();
        $line->shipped_quantity = 1;
        $line->save();

        $service = app(OrderLineCancellationService::class);

        $this->expectException(\RuntimeException::class);
        $service->cancelLines($order, [[
            'order_product_id' => $line->id,
            'quantity' => 2,
            'reason' => 'خطأ',
        ]], 1);
    }

    public function test_full_cancel_when_all_unshipped_lines_cancelled(): void
    {
        $order = $this->createOrderWithProducts([
            ['category_id' => 1, 'quantity' => 2, 'price' => 100.0],
        ], [
            'total_invoice' => 200.0,
            'net_total' => 200.0,
        ]);

        $line = OrderProduct::query()->where('order_id', $order->id)->firstOrFail();

        $service = app(OrderLineCancellationService::class);
        $result = $service->cancelLines($order, [[
            'order_product_id' => $line->id,
            'quantity' => 2,
            'reason' => 'طلب العميل',
        ]], 1);

        $this->assertTrue($result['full_cancelled']);
        $order->refresh();
        $this->assertSame('ملغي', $order->order_status);
    }

    public function test_cancel_lines_api_endpoint(): void
    {
        $user = User::query()->where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);
        $this->assertNotNull($user);

        $order = $this->createOrderWithProducts([
            ['category_id' => 1, 'quantity' => 1, 'price' => 120.0],
        ], [
            'total_invoice' => 120.0,
            'net_total' => 120.0,
        ]);

        $line = OrderProduct::query()->where('order_id', $order->id)->firstOrFail();

        $response = $this->actingAs($user, 'api')->postJson("/api/orders/{$order->id}/cancel-lines", [
            'lines' => [[
                'order_product_id' => $line->id,
                'quantity' => 1,
                'reason' => 'اختبار API',
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('full_cancelled', true);
    }

    /**
     * @param  array<int, array{category_id: int, quantity: float|int, price: float}>  $products
     * @param  array<string, float|int|string>  $overrides
     */
    private function createOrderWithProducts(array $products, array $overrides = []): Order
    {
        $orderSource = OrderSource::firstOrCreate(['name' => 'Test '.$this->codePrefix]);

        return Order::withoutEvents(function () use ($products, $overrides, $orderSource) {
            $order = Order::create(array_merge([
                'customer_name' => 'عميل '.$this->codePrefix,
                'customer_type' => 'فرد',
                'customer_phone_1' => '010'.substr($this->codePrefix, -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'address' => 'شارع تجريبي',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => $orderSource->id,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 100.0,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 100.0,
                'order_status' => 'طلب مؤكد',
            ], $overrides));

            foreach ($products as $row) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'category_id' => $row['category_id'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'total_price' => $row['quantity'] * $row['price'],
                ]);
            }

            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });
    }
}
