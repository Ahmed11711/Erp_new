<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Services\Shipping\OrderLiabilityTransferService;
use Mockery;
use Tests\TestCase;

class OrderLiabilityTransferServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): OrderLiabilityTransferService
    {
        return new OrderLiabilityTransferService(
            Mockery::mock(\App\Services\Accounting\DeliveryConfirmationAccountingService::class),
            Mockery::mock(\App\Services\Shipping\OrderFinancialStateService::class),
        );
    }

    public function test_resolve_transfer_amount_uses_remaining_for_cod_orders(): void
    {
        $order = new Order(['net_total' => 1384.60]);
        $od = new OrderDetails([
            'remaining_amount' => 1384.60,
            'collection_receivable_amount' => 0,
        ]);

        $amount = $this->service()->resolveTransferAmount($order, $od);

        $this->assertEqualsWithDelta(1384.60, $amount, 0.01);
    }

    public function test_resolve_transfer_amount_uses_collection_receivable_for_prepaid_orders(): void
    {
        $order = new Order(['net_total' => 1308.00, 'prepaid_amount' => 1308.00]);
        $od = new OrderDetails([
            'remaining_amount' => 0,
            'collection_receivable_amount' => 1308.00,
        ]);

        $amount = $this->service()->resolveTransferAmount($order, $od);

        $this->assertEqualsWithDelta(1308.00, $amount, 0.01);
    }

    public function test_can_transfer_liability_true_for_prepaid_shipped_order(): void
    {
        $order = new Order([
            'net_total' => 1308.00,
            'prepaid_amount' => 1308.00,
            'order_status' => 'تم شحن',
        ]);
        $od = new OrderDetails([
            'remaining_amount' => 0,
            'collection_receivable_amount' => 1308.00,
            'liability_transferred_at' => null,
        ]);
        $order->setRelation('order_details', $od);

        $this->assertTrue($this->service()->canTransferLiability($order, $od));
    }

    public function test_can_transfer_liability_false_when_already_transferred(): void
    {
        $order = new Order([
            'net_total' => 1308.00,
            'prepaid_amount' => 1308.00,
            'order_status' => 'تم شحن',
        ]);
        $od = new OrderDetails([
            'remaining_amount' => 0,
            'collection_receivable_amount' => 1308.00,
            'liability_transferred_at' => now(),
        ]);
        $order->setRelation('order_details', $od);

        $this->assertFalse($this->service()->canTransferLiability($order, $od));
    }

    public function test_is_prepaid_collection_order_when_remaining_zero_and_collection_receivable_open(): void
    {
        $order = new Order(['net_total' => 16792]);
        $od = new OrderDetails([
            'remaining_amount' => 0,
            'collection_receivable_amount' => 16792,
        ]);
        $order->setRelation('order_details', $od);

        $this->assertTrue($this->service()->isPrepaidCollectionOrder($order, $od));
    }

    public function test_resolve_manual_transfer_holder_allows_courier_for_prepaid_order(): void
    {
        $order = new Order([
            'net_total' => 16792,
            'prepaid_amount' => 16792,
        ]);
        $od = new OrderDetails([
            'remaining_amount' => 0,
            'collection_receivable_amount' => 16792,
            'shipping_provider_id' => 55,
            'shipping_company_id' => 55,
        ]);
        $order->setRelation('order_details', $od);

        $holder = $this->service()->resolveManualTransferHolder(
            $order,
            \App\Enums\LiabilityHolderType::Courier
        );

        $this->assertNotNull($holder);
        $this->assertContains($holder[0], [
            \App\Enums\LiabilityHolderType::Courier,
            \App\Enums\LiabilityHolderType::ShippingCompany,
        ]);
        $this->assertSame(55, $holder[1]);
    }

    public function test_resolve_manual_transfer_holder_uses_preferred_collection_company_id(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('collection_companies')) {
            $this->markTestSkipped('collection_companies table missing');
        }

        $company = \App\Models\CollectionCompany::query()->create([
            'name' => 'Paymob Test ' . uniqid(),
            'status' => 'active',
        ]);

        $order = new Order([
            'net_total' => 1000,
            'prepaid_amount' => 1000,
        ]);
        $od = new class extends OrderDetails {
            public bool $saved = false;

            public function save(array $options = [])
            {
                $this->saved = true;

                return true;
            }
        };
        $od->collection_provider_type = 'none';
        $od->collection_provider_id = null;
        $od->collection_company_id = null;
        $order->setRelation('order_details', $od);
        $order->setRelation('order_details.shipping_company', null);

        $holder = $this->service()->resolveManualTransferHolder(
            $order,
            \App\Enums\LiabilityHolderType::CollectionCompany,
            (int) $company->id,
        );

        $this->assertNotNull($holder);
        $this->assertSame(\App\Enums\LiabilityHolderType::CollectionCompany, $holder[0]);
        $this->assertSame((int) $company->id, $holder[1]);
        $this->assertTrue($od->saved);
        $this->assertSame('collection_company', $od->collection_provider_type);
        $this->assertSame((int) $company->id, (int) $od->collection_provider_id);
    }
}
