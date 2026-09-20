<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\Orders\OrderPartialFulfillmentService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OrderPartialDeliveryStatusTest extends TestCase
{
    private function makeOrder(string $orderType, float $qty, float $shipped, float $cancelled = 0): Order
    {
        $order = new Order(['order_type' => $orderType]);
        $order->order_type = $orderType;

        $line = new OrderProduct([
            'quantity' => $qty,
            'shipped_quantity' => $shipped,
            'cancelled_quantity' => $cancelled,
        ]);

        $order->setRelation('order_products', new Collection([$line]));

        return $order;
    }

    public function test_maintenance_order_closes_as_delivered_even_without_line_quantities(): void
    {
        $service = app(OrderPartialFulfillmentService::class);
        $order = $this->makeOrder('طلب صيانة', qty: 1, shipped: 0);

        $this->assertFalse($service->tracksPartialFulfillment($order));
        $this->assertFalse($service->shouldRecordPartialDelivery($order));
        $this->assertSame(
            OrderPartialFulfillmentService::STATUS_DELIVERED,
            $service->statusAfterDeliver($order)
        );
    }

    public function test_new_order_with_unshipped_quantity_stays_partially_delivered(): void
    {
        $service = app(OrderPartialFulfillmentService::class);
        $order = $this->makeOrder('جديد', qty: 3, shipped: 1);

        $this->assertTrue($service->shouldRecordPartialDelivery($order));
        $this->assertSame(
            OrderPartialFulfillmentService::STATUS_PARTIAL_DELIVER,
            $service->statusAfterDeliver($order)
        );
    }

    public function test_mark_all_lines_shipped_skips_negative_return_lines(): void
    {
        $service = app(OrderPartialFulfillmentService::class);
        $order = $this->makeOrder('طلب مرتجع', qty: -1, shipped: -1);

        $this->assertSame(0, $service->markAllLinesShipped($order));
        $this->assertSame(-1.0, (float) $order->order_products[0]->shipped_quantity);
    }

    public function test_new_order_fully_shipped_closes_as_delivered(): void
    {
        $service = app(OrderPartialFulfillmentService::class);
        $order = $this->makeOrder('جديد', qty: 3, shipped: 2, cancelled: 1);

        $this->assertFalse($service->shouldRecordPartialDelivery($order));
        $this->assertSame(
            OrderPartialFulfillmentService::STATUS_DELIVERED,
            $service->statusAfterDeliver($order)
        );
    }
}
