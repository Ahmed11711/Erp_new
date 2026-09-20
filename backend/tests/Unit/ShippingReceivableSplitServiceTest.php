<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Shipping\ShippingReceivableSplitService;
use InvalidArgumentException;
use Tests\TestCase;

class ShippingReceivableSplitServiceTest extends TestCase
{
    private ShippingReceivableSplitService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ShippingReceivableSplitService();
    }

    public function test_mixed_online_plus_cash_item_puts_cod_on_courier_and_prepaid_on_paymob(): void
    {
        $order = new Order([
            'net_total' => 250,
            'prepaid_amount' => 2985,
        ]);

        $split = $this->svc->resolveForShip($order, 10, 99, null, null);

        $this->assertEqualsWithDelta(250.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(2985.0, $split['collection_amount'], 0.001);
        $this->assertSame(99, $split['collection_company_id']);
    }

    public function test_incomplete_manual_split_falls_back_to_auto_instead_of_throwing(): void
    {
        $order = new Order([
            'net_total' => 250,
            'prepaid_amount' => 2985,
        ]);

        $split = $this->svc->resolveForShip($order, 10, 99, null, 2985.0);

        $this->assertEqualsWithDelta(250.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(2985.0, $split['collection_amount'], 0.001);
    }

    public function test_manual_split_accepts_sum_equal_to_prepaid_plus_remaining(): void
    {
        $order = new Order([
            'net_total' => 250,
            'prepaid_amount' => 2985,
        ]);

        $split = $this->svc->resolveForShip($order, 10, 99, 250.0, 2985.0);

        $this->assertEqualsWithDelta(250.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(2985.0, $split['collection_amount'], 0.001);
    }

    public function test_partial_prepaid_keeps_legacy_auto_split(): void
    {
        $order = new Order([
            'net_total' => 90,
            'prepaid_amount' => 40,
        ]);

        $split = $this->svc->resolveForShip($order, 10, 99, null, null);

        $this->assertEqualsWithDelta(50.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(40.0, $split['collection_amount'], 0.001);
    }

    public function test_cod_only_stays_on_courier(): void
    {
        $order = new Order([
            'net_total' => 1000,
            'prepaid_amount' => 0,
        ]);

        $split = $this->svc->resolveForShip($order, 10, 99, null, null);

        $this->assertEqualsWithDelta(1000.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(0.0, $split['collection_amount'], 0.001);
        $this->assertNull($split['collection_company_id']);
    }

    public function test_mixed_split_without_legacy_collection_company_id_does_not_block_delivery(): void
    {
        $order = new Order([
            'net_total' => 200,
            'prepaid_amount' => 3842,
        ]);

        $split = $this->svc->resolveForShip($order, 10, null, 200.0, 3842.0);

        $this->assertEqualsWithDelta(200.0, $split['shipping_amount'], 0.001);
        $this->assertEqualsWithDelta(3842.0, $split['collection_amount'], 0.001);
        $this->assertNull($split['collection_company_id']);
    }

    public function test_invalid_manual_sum_still_throws(): void
    {
        $order = new Order([
            'net_total' => 250,
            'prepaid_amount' => 2985,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->svc->resolveForShip($order, 10, 99, 100.0, 100.0);
    }

    public function test_cod_remaining_when_prepaid_exceeds_net(): void
    {
        $this->assertEqualsWithDelta(250.0, $this->svc->codRemaining(250, 2985), 0.001);
        $this->assertEqualsWithDelta(50.0, $this->svc->codRemaining(90, 40), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->svc->codRemaining(0, 2985), 0.001);
    }
}
