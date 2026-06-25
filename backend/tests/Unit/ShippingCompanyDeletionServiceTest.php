<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Services\Shipping\ShippingCompanyDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShippingCompanyDeletionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_blocks_deletion_when_linked_to_orders(): void
    {
        $company = ShippingCompany::create([
            'name' => 'مندوب اختبار',
            'type' => 'مندوب',
            'balance' => 0,
        ]);

        $order = Order::withoutEvents(fn () => Order::create([
            'customer_name' => 'Test Delete Block',
            'customer_type' => 'فرد',
            'customer_phone_1' => '01099998888',
            'customer_phone_2' => '',
            'governorate' => 'القاهرة',
            'city' => 'مدينة نصر',
            'address' => 'test',
            'order_date' => now()->toDateString(),
            'shipping_method_id' => 1,
            'order_source_id' => 1,
            'order_type' => 'جديد',
            'total_invoice' => 100,
            'net_total' => 100,
            'order_status' => 'طلب مؤكد',
        ]));

        OrderDetails::create([
            'order_id' => $order->id,
            'shipping_company_id' => $company->id,
        ]);

        $service = app(ShippingCompanyDeletionService::class);
        $reason = $service->getBlockReason($company);

        $this->assertNotNull($reason);
        $this->assertStringContainsString('1', $reason);
        $this->assertStringContainsString('طلب', $reason);

        $this->expectException(\InvalidArgumentException::class);
        $service->delete($company);
    }

    public function test_deletes_unlinked_shipping_company(): void
    {
        $company = ShippingCompany::create([
            'name' => 'مندوب فارغ',
            'type' => 'مندوب',
            'balance' => 0,
        ]);

        app(ShippingCompanyDeletionService::class)->delete($company);

        $this->assertDatabaseMissing('shipping_companies', ['id' => $company->id]);
    }
}
