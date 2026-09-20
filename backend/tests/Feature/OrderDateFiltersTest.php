<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Orders\OrderStatusVisibilityService;
use App\Services\Rbac\RbacStampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDateFiltersTest extends TestCase
{
    use DatabaseTransactions;

    private string $phonePrefix;

    protected function setUp(): void
    {
        parent::setUp();
        $this->phonePrefix = '0188' . substr(str_replace('.', '', uniqid('', true)), -7);
        $this->ensureStatusPermissionsExist();
    }

    public function test_order_date_exact_day_filter(): void
    {
        $user = $this->makeIsolatedUser(['new']);
        $inRange = $this->makeOrder('طلب جديد', '2026-07-10');
        $outOfRange = $this->makeOrder('طلب جديد', '2026-07-11');

        $response = $this->actingAs($user, 'api')->getJson(
            '/api/orders/search?order_date=2026-07-10&itemsPerPage=100'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($inRange->id, $ids);
        $this->assertNotContains($outOfRange->id, $ids);
    }

    public function test_order_date_from_to_range_filter(): void
    {
        $user = $this->makeIsolatedUser(['new']);
        $before = $this->makeOrder('طلب جديد', '2026-07-01');
        $inside = $this->makeOrder('طلب جديد', '2026-07-15');
        $after = $this->makeOrder('طلب جديد', '2026-07-31');

        $response = $this->actingAs($user, 'api')->getJson(
            '/api/orders/search?order_date_from=2026-07-10&order_date_to=2026-07-20&itemsPerPage=100'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($before->id, $ids);
        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($after->id, $ids);
    }

    public function test_status_date_range_filter(): void
    {
        $user = $this->makeIsolatedUser(['new']);
        $inside = $this->makeOrder('طلب جديد', '2026-07-15', [
            'status_date' => '2026-07-12',
        ]);
        $outside = $this->makeOrder('طلب جديد', '2026-07-15', [
            'status_date' => '2026-07-25',
        ]);

        $response = $this->actingAs($user, 'api')->getJson(
            '/api/orders/search?status_date_from=2026-07-10&status_date_to=2026-07-15&itemsPerPage=100'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($inside->id, $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_need_by_date_exact_and_delivery_range(): void
    {
        $user = $this->makeIsolatedUser(['new']);
        $match = $this->makeOrder('طلب جديد', '2026-07-15', [
            'need_by_date' => '2026-07-18',
        ], '2026-07-20');
        $other = $this->makeOrder('طلب جديد', '2026-07-15', [
            'need_by_date' => '2026-07-19',
        ], '2026-07-28');

        $byDay = $this->actingAs($user, 'api')->getJson(
            '/api/orders/search?need_by_date=2026-07-18&itemsPerPage=100'
        );
        $byDay->assertOk();
        $dayIds = collect($byDay->json('data'))->pluck('id')->all();
        $this->assertContains($match->id, $dayIds);
        $this->assertNotContains($other->id, $dayIds);

        $byDelivery = $this->actingAs($user, 'api')->getJson(
            '/api/orders/search?delivery_date_from=2026-07-19&delivery_date_to=2026-07-22&itemsPerPage=100'
        );
        $byDelivery->assertOk();
        $deliveryIds = collect($byDelivery->json('data'))->pluck('id')->all();
        $this->assertContains($match->id, $deliveryIds);
        $this->assertNotContains($other->id, $deliveryIds);
    }

    private function ensureStatusPermissionsExist(): void
    {
        $guard = config('auth.defaults.guard');

        Permission::query()->firstOrCreate(
            ['slug' => 'orders.view', 'guard_name' => $guard],
            ['name' => 'View Orders', 'module' => 'orders']
        );

        foreach (app(OrderStatusVisibilityService::class)->permissionDefinitions() as $def) {
            Permission::query()->firstOrCreate(
                ['slug' => $def['slug'], 'guard_name' => $guard],
                ['name' => $def['name'], 'module' => 'orders_statuses']
            );
        }
    }

    private function makeIsolatedUser(array $statusKeys): User
    {
        $guard = config('auth.defaults.guard');
        $user = User::factory()->create([
            'department' => 'ODF-' . Str::random(10),
            'email' => 'odf_' . Str::lower(Str::random(8)) . '@test.local',
        ]);

        $slugs = array_merge(
            ['orders.view'],
            array_map(static fn ($key) => 'orders.view_status.' . $key, $statusKeys)
        );

        $role = Role::query()->create([
            'name' => 'ODF Role ' . Str::random(6),
            'slug' => 'odf-' . Str::lower(Str::random(12)),
            'guard_name' => $guard,
        ]);

        $ids = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();

        $role->syncPermissions($ids);
        $user->roles()->sync([$role->id]);
        app(RbacStampService::class)->bumpUser($user->id);

        return $user->fresh();
    }

    private function makeOrder(
        string $status,
        string $orderDate,
        array $details = [],
        ?string $deliveryDate = null
    ): Order {
        static $seq = 0;
        $seq++;

        return Order::withoutEvents(function () use ($status, $orderDate, $details, $deliveryDate, $seq) {
            $order = Order::create([
                'customer_name' => 'عميل فلتر تاريخ',
                'customer_type' => 'فرد',
                'customer_phone_1' => $this->phonePrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => $orderDate,
                'delivery_date' => $deliveryDate,
                'shipping_method_id' => 1,
                'order_source_id' => 1,
                'order_type' => 'جديد',
                'shipping_cost' => 0,
                'total_invoice' => 100,
                'prepaid_amount' => 0,
                'discount' => 0,
                'net_total' => 100,
                'order_status' => $status,
            ]);

            OrderDetails::create(array_merge(['order_id' => $order->id], $details));

            return $order;
        });
    }
}
