<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\OrderShipment;
use App\Models\OrderShipmentLine;
use App\Models\OrderSource;
use App\Models\ShippingCompany;
use App\Services\Orders\OrderPartialFulfillmentService;
use App\Services\Shipping\CourierInHandInventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CourierInHandInventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $prefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'CIH'.substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_in_hand_lists_undelivered_shipment_lines_and_sku_summary(): void
    {
        $company = $this->makeCourier();
        $other = $this->makeCourier('other');
        $catA = $this->makeCategory('صنف أ');
        $catB = $this->makeCategory('صنف ب');

        $openOrder = $this->makeOrder('تم شحن', [
            ['category' => $catA, 'qty' => 5, 'shipped' => 3, 'price' => 10],
            ['category' => $catB, 'qty' => 2, 'shipped' => 2, 'price' => 20],
        ], $company);
        $this->makeShipment($openOrder, $company, [
            ['product' => $openOrder->order_products[0], 'qty' => 3],
            ['product' => $openOrder->order_products[1], 'qty' => 2],
        ], delivered: false);

        $deliveredOrder = $this->makeOrder('تم التسليم', [
            ['category' => $catA, 'qty' => 1, 'shipped' => 1, 'price' => 10],
        ], $company);
        $this->makeShipment($deliveredOrder, $company, [
            ['product' => $deliveredOrder->order_products[0], 'qty' => 1],
        ], delivered: true);

        $otherOrder = $this->makeOrder('تم شحن', [
            ['category' => $catA, 'qty' => 4, 'shipped' => 4, 'price' => 10],
        ], $other);
        $this->makeShipment($otherOrder, $other, [
            ['product' => $otherOrder->order_products[0], 'qty' => 4],
        ], delivered: false);

        $report = app(CourierInHandInventoryService::class)->forCompany((int) $company->id);

        $this->assertSame(1, $report['totals']['orders_count']);
        $this->assertEqualsWithDelta(5.0, $report['totals']['in_hand_qty'], 0.001);
        $this->assertEqualsWithDelta(70.0, $report['totals']['in_hand_value'], 0.01);
        $this->assertSame(1, $report['totals']['deliverable_count']);
        $this->assertSame(OrderPartialFulfillmentService::STATUS_PARTIAL_DELIVER, $report['orders'][0]['will_mark_status']);
        $this->assertEqualsWithDelta(2.0, $report['orders'][0]['remaining_unshipped_qty'], 0.001);

        $skuNames = array_column($report['sku_summary'], 'product_name');
        $this->assertContains($catA->category_name, $skuNames);
        $this->assertContains($catB->category_name, $skuNames);
    }

    public function test_marking_shipments_delivered_removes_them_from_in_hand(): void
    {
        $company = $this->makeCourier();
        $cat = $this->makeCategory('صنف تسليم');
        $order = $this->makeOrder('تم شحن', [
            ['category' => $cat, 'qty' => 2, 'shipped' => 2, 'price' => 15],
        ], $company);
        $this->makeShipment($order, $company, [
            ['product' => $order->order_products[0], 'qty' => 2],
        ], delivered: false);

        app(OrderPartialFulfillmentService::class)->markUndeliveredShipmentsDelivered($order, 1);

        $report = app(CourierInHandInventoryService::class)->forCompany((int) $company->id);
        $this->assertSame(0, $report['totals']['orders_count']);
        $this->assertEqualsWithDelta(0.0, $report['totals']['in_hand_qty'], 0.001);
    }

    public function test_legacy_shipped_details_appear_when_no_shipment_rows(): void
    {
        $company = $this->makeCourier();
        $cat = $this->makeCategory('صنف قديم');
        $order = $this->makeOrder('شحن جزئي', [
            ['category' => $cat, 'qty' => 4, 'shipped' => 1, 'price' => 50],
        ], $company);

        \App\Models\shippingCompanyDetails::create([
            'order_id' => $order->id,
            'shipping_company_id' => $company->id,
            'status' => 'تم شحن',
            'amount' => 50,
            'is_done' => 0,
            'shipping_date' => now()->toDateString(),
            'by' => 'test',
            'ref' => $this->prefix,
        ]);

        $report = app(CourierInHandInventoryService::class)->forCompany((int) $company->id);

        $this->assertSame(1, $report['totals']['orders_count']);
        $this->assertEqualsWithDelta(1.0, $report['totals']['in_hand_qty'], 0.001);
        $this->assertEqualsWithDelta(50.0, $report['totals']['in_hand_value'], 0.01);
        $this->assertSame(OrderPartialFulfillmentService::STATUS_PARTIAL_DELIVER, $report['orders'][0]['will_mark_status']);
    }

    private function makeCourier(string $suffix = 'main'): ShippingCompany
    {
        return ShippingCompany::create([
            'name' => 'مندوب '.$this->prefix.$suffix,
            'type' => 'مندوب',
        ]);
    }

    private function makeCategory(string $name): Category
    {
        return Category::query()->create([
            'category_name' => $name.' '.$this->prefix,
            'warehouse' => 'مخزن مواد خام',
            'category_price' => 1,
            'unit_price' => 1,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'category_image' => '',
            'quantity' => 100,
        ]);
    }

    /**
     * @param  list<array{category: Category, qty: float|int, shipped: float|int, price: float|int}>  $products
     */
    private function makeOrder(string $status, array $products, ShippingCompany $company): Order
    {
        $source = OrderSource::firstOrCreate(['name' => 'Test '.$this->prefix]);

        return Order::withoutEvents(function () use ($status, $products, $company, $source) {
            $net = 0.0;
            foreach ($products as $p) {
                $net += $p['qty'] * $p['price'];
            }

            $order = Order::create([
                'customer_name' => 'عميل '.$this->prefix,
                'customer_type' => 'افراد',
                'customer_phone_1' => '010'.substr($this->prefix, -8),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'مدينة نصر',
                'address' => 'شارع تجريبي',
                'order_date' => now()->toDateString(),
                'shipping_method_id' => 1,
                'order_source_id' => $source->id,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => $net,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => $net,
                'order_status' => $status,
            ]);

            foreach ($products as $p) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'category_id' => $p['category']->id,
                    'quantity' => $p['qty'],
                    'shipped_quantity' => $p['shipped'],
                    'price' => $p['price'],
                    'total_price' => $p['qty'] * $p['price'],
                ]);
            }

            OrderDetails::create([
                'order_id' => $order->id,
                'shipping_company_id' => $company->id,
                'shipping_date' => now()->toDateString(),
            ]);

            return $order->fresh(['order_products']);
        });
    }

    /**
     * @param  list<array{product: OrderProduct, qty: float|int}>  $lines
     */
    private function makeShipment(Order $order, ShippingCompany $company, array $lines, bool $delivered): void
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += $line['qty'] * (float) $line['product']->price;
        }

        $seq = (int) OrderShipment::query()->where('order_id', $order->id)->max('shipment_seq') + 1;
        $shipment = OrderShipment::create([
            'order_id' => $order->id,
            'shipment_seq' => $seq,
            'shipped_at' => now()->toDateString(),
            'shipping_company_id' => $company->id,
            'lines_total' => $total,
            'cogs_total' => 0,
            'is_final' => false,
            'status_after' => $order->order_status,
            'delivered_at' => $delivered ? now() : null,
            'delivered_by' => $delivered ? 1 : null,
        ]);

        foreach ($lines as $line) {
            $qty = (float) $line['qty'];
            $price = (float) $line['product']->price;
            OrderShipmentLine::create([
                'order_shipment_id' => $shipment->id,
                'order_product_id' => $line['product']->id,
                'category_id' => $line['product']->category_id,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => $qty * $price,
                'unit_cost' => 0,
                'line_cogs' => 0,
            ]);
        }
    }
}
