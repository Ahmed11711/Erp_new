<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderRollbackAudit;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Orders\OrderRollbackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OrderRollbackTest extends TestCase
{
    use DatabaseTransactions;

    private function minimalOrderAttributes(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Test Rollback',
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
        ], $overrides);
    }

    public function test_preview_rejects_ineligible_status(): void
    {
        $order = Order::withoutEvents(fn () => Order::create($this->minimalOrderAttributes()));

        $svc = app(OrderRollbackService::class);
        $this->assertFalse($svc->isEligible($order));
    }

    public function test_rollback_from_delivered_to_confirmed_records_audit_and_history(): void
    {
        $user = User::query()->first();
        $this->actingAs($user, 'api');

        $order = Order::withoutEvents(fn () => Order::create($this->minimalOrderAttributes([
            'customer_name' => 'Rollback Delivered',
            'customer_phone_1' => '01077776666',
            'total_invoice' => 500,
            'net_total' => 500,
            'order_status' => 'تم التسليم',
        ])));

        OrderDetails::create([
            'order_id' => $order->id,
            'confirm_date' => now()->toDateString(),
            'shipping_date' => now()->toDateString(),
            'delivery_date' => now()->toDateString(),
        ]);

        $svc = app(OrderRollbackService::class);
        $this->assertTrue($svc->isEligible($order));

        $result = $svc->execute(
            $order,
            \App\Enums\OrderRollbackTarget::Confirmed,
            'اختبار إعادة فتح',
            (int) $user->id,
        );

        $this->assertSame('طلب مؤكد', $result['new_status']);

        $order->refresh();
        $this->assertSame('طلب مؤكد', $order->order_status);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'old_status' => 'تم التسليم',
            'new_status' => 'طلب مؤكد',
        ]);

        $this->assertTrue(
            OrderRollbackAudit::query()->where('order_id', $order->id)->exists()
        );
    }

    public function test_api_preview_requires_eligible_status(): void
    {
        $user = User::query()->first();
        $this->actingAs($user, 'api');

        $order = Order::withoutEvents(fn () => Order::create($this->minimalOrderAttributes([
            'customer_name' => 'API Preview',
            'customer_phone_1' => '01066665555',
            'order_status' => 'طلب جديد',
        ])));

        $this->getJson("/api/orders/{$order->id}/rollback/preview?target=confirmed")
            ->assertStatus(422);
    }
}
