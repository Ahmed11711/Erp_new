<?php

namespace Tests\Unit;

use App\Enums\CollectionProviderType;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Services\Shipping\OrderFinancialStateService;
use App\Services\Shipping\ShippingReceivableSplitService;
use Tests\TestCase;

class OrderFinancialStateMixedPrepaidTest extends TestCase
{
    public function test_adding_cash_item_to_online_order_sets_remaining_and_both_split_amounts(): void
    {
        $order = new Order([
            'net_total' => 250,
            'prepaid_amount' => 2985,
            'order_status' => 'تم شحن',
        ]);

        $od = new class extends OrderDetails {
            public function save(array $options = [])
            {
                return true;
            }
        };
        $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
        $od->collection_provider_id = 7;
        $od->collection_receivable_amount = 2985;
        $od->shipping_receivable_amount = null;
        $od->shipping_company_id = 10;
        $order->setRelation('order_details', $od);

        $svc = new OrderFinancialStateService(new ShippingReceivableSplitService());
        $result = $svc->syncFromOrder($order, $od);

        $this->assertEqualsWithDelta(250.0, (float) $result->remaining_amount, 0.001);
        $this->assertEqualsWithDelta(250.0, (float) $result->amount_to_collect, 0.001);
        $this->assertEqualsWithDelta(250.0, (float) $result->shipping_receivable_amount, 0.001);
        $this->assertEqualsWithDelta(2985.0, (float) $result->collection_receivable_amount, 0.001);
    }

    public function test_fully_prepaid_electronic_order_stores_zero_shipping_receivable_not_null(): void
    {
        $order = new Order([
            'net_total' => 0,
            'prepaid_amount' => 2985,
            'order_status' => 'تم شحن',
        ]);

        $od = new class extends OrderDetails {
            public function save(array $options = [])
            {
                return true;
            }
        };
        $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
        $od->collection_provider_id = 7;
        $order->setRelation('order_details', $od);

        $svc = new OrderFinancialStateService(new ShippingReceivableSplitService());
        $result = $svc->syncFromOrder($order, $od);

        $this->assertEqualsWithDelta(0.0, (float) $result->remaining_amount, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $result->shipping_receivable_amount, 0.001);
        $this->assertEqualsWithDelta(2985.0, (float) $result->collection_receivable_amount, 0.001);
        $this->assertNotNull($result->shipping_receivable_amount);
    }
}
