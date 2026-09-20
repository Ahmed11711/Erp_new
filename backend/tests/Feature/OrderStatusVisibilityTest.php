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

/**
 * عرض الطلبات قائم بالكامل على صلاحيات الحالة (orders.view_status.*).
 * المستخدم يرى الطلب فقط إذا كان يملك صلاحية حالته الحالية.
 */
class OrderStatusVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private OrderStatusVisibilityService $visibility;

    private string $phonePrefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibility = app(OrderStatusVisibilityService::class);
        $this->phonePrefix = '0199' . substr(str_replace('.', '', uniqid('', true)), -7);
        $this->ensureStatusPermissionsExist();
    }

    // ---------------------------------------------------------------------
    // المنطق القائم على الصلاحيات (مستخدمون معزولون عن قوالب الأقسام)
    // ---------------------------------------------------------------------

    public function test_super_admin_email_bypasses_status_filter(): void
    {
        $user = $this->makeIsolatedUser([]);
        config(['rbac.super_admin_emails' => [strtolower($user->email)]]);

        $this->assertTrue($this->visibility->bypassesStatusFilter($user));
        $this->assertContains('طلب جديد', $this->visibility->visibleStatusLabels($user));
    }

    public function test_user_without_any_status_permission_sees_nothing(): void
    {
        $user = $this->makeIsolatedUser([]);

        $this->assertSame([], $this->visibility->visibleStatusLabels($user));
        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب جديد'));
        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب مؤكد'));
    }

    public function test_user_sees_only_granted_statuses(): void
    {
        $user = $this->makeIsolatedUser(['confirmed']);

        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب مؤكد'));
        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب جديد'));
        $this->assertFalse($this->visibility->canViewStatus($user, 'تم شحن'));
    }

    public function test_user_with_new_permission_sees_new(): void
    {
        $user = $this->makeIsolatedUser(['new']);

        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب جديد'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'جديد'));
        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب مؤكد'));
    }

    public function test_search_excludes_ungranted_statuses(): void
    {
        $user = $this->makeIsolatedUser(['confirmed']);
        $newOrder = $this->makeOrder('طلب جديد');
        $confirmedOrder = $this->makeOrder('طلب مؤكد');

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/search?itemsPerPage=100');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($newOrder->id, $ids);
        $this->assertContains($confirmedOrder->id, $ids);
    }

    public function test_search_returns_empty_when_user_has_no_status_permission(): void
    {
        $user = $this->makeIsolatedUser([]);
        $this->makeOrder('طلب جديد');
        $this->makeOrder('طلب مؤكد');

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/search?itemsPerPage=100');

        $response->assertOk();
        $this->assertSame(0, (int) $response->json('total'));
    }

    public function test_search_status_filter_returns_empty_when_not_granted(): void
    {
        $user = $this->makeIsolatedUser(['confirmed']);
        $this->makeOrder('طلب جديد');

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/search?order_status=طلب جديد&itemsPerPage=100');

        $response->assertOk();
        $this->assertSame(0, (int) $response->json('total'));
    }

    public function test_show_returns_403_for_ungranted_status(): void
    {
        $user = $this->makeIsolatedUser(['confirmed']);
        $order = $this->makeOrder('طلب جديد');

        $this->actingAs($user, 'api')
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_show_returns_order_for_granted_status(): void
    {
        $user = $this->makeIsolatedUser(['confirmed']);
        $order = $this->makeOrder('طلب مؤكد');

        $this->actingAs($user, 'api')
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('order_status', 'طلب مؤكد');
    }

    public function test_visible_statuses_endpoint_reflects_permissions(): void
    {
        $user = $this->makeIsolatedUser(['new', 'confirmed']);

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/visible-statuses');

        $response->assertOk();
        $values = collect($response->json('statuses'))->pluck('value')->all();

        $this->assertContains('طلب جديد', $values);
        $this->assertContains('طلب مؤكد', $values);
        $this->assertNotContains('تم شحن', $values);
    }

    public function test_visible_statuses_endpoint_empty_without_permissions(): void
    {
        $user = $this->makeIsolatedUser([]);

        $response = $this->actingAs($user, 'api')->getJson('/api/orders/visible-statuses');

        $response->assertOk();
        $this->assertSame([], $response->json('statuses'));
    }

    // ---------------------------------------------------------------------
    // قوالب الأقسام المزروعة (تعكس الإعداد الافتراضي بعد الـ seeder)
    // ---------------------------------------------------------------------

    public function test_department_customer_service_sees_new(): void
    {
        $user = $this->makeDeptUser('Customer Service');

        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب جديد'));
    }

    public function test_department_shipping_management_hides_new(): void
    {
        $user = $this->makeDeptUser('Shipping Management');

        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب جديد'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب مؤكد'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'تم شحن'));
    }

    public function test_department_admin_sees_all_except_new(): void
    {
        $user = $this->makeDeptUser('Admin');

        $this->assertFalse(
            $this->visibility->canViewStatus($user, 'طلب جديد'),
            'New orders are restricted to Customer Service only'
        );
        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب مؤكد'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'تم التحصيل'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'ملغي'));
    }

    public function test_only_customer_service_sees_new_by_default(): void
    {
        $cs = $this->makeDeptUser('Customer Service');
        $admin = $this->makeDeptUser('Admin');
        $shipping = $this->makeDeptUser('Shipping Management');
        $dataEntry = $this->makeDeptUser('Data Entry');

        $this->assertTrue($this->visibility->canViewStatus($cs, 'طلب جديد'));
        $this->assertFalse($this->visibility->canViewStatus($admin, 'طلب جديد'));
        $this->assertFalse($this->visibility->canViewStatus($shipping, 'طلب جديد'));
        $this->assertFalse($this->visibility->canViewStatus($dataEntry, 'طلب جديد'));
    }

    public function test_department_data_entry_hides_new(): void
    {
        $user = $this->makeDeptUser('Data Entry');

        $this->assertFalse($this->visibility->canViewStatus($user, 'طلب جديد'));
        $this->assertTrue($this->visibility->canViewStatus($user, 'طلب مؤكد'));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

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

    /**
     * مستخدم معزول في قسم فريد (لا ينطبق عليه قالب قسم) بدور يمنح حالات محددة فقط.
     *
     * @param  list<string>  $statusKeys
     */
    private function makeIsolatedUser(array $statusKeys): User
    {
        $guard = config('auth.defaults.guard');

        $user = User::factory()->create([
            'department' => 'OSV-' . Str::random(10),
            'email' => 'osv_' . Str::lower(Str::random(8)) . '@test.local',
        ]);

        $slugs = array_merge(
            ['orders.view'],
            array_map(static fn ($key) => 'orders.view_status.' . $key, $statusKeys)
        );

        $role = Role::query()->create([
            'name' => 'OSV Role ' . Str::random(6),
            'slug' => 'osv-' . Str::lower(Str::random(12)),
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

    private function makeDeptUser(string $department): User
    {
        $user = User::factory()->create([
            'department' => $department,
            'email' => 'osv_' . Str::lower(Str::random(8)) . '@test.local',
        ]);
        app(RbacStampService::class)->bumpUser($user->id);

        return $user->fresh();
    }

    private function makeOrder(string $status): Order
    {
        static $seq = 0;
        $seq++;

        return Order::withoutEvents(function () use ($status, $seq) {
            $order = Order::create([
                'customer_name' => 'عميل اختبار حالة',
                'customer_type' => 'فرد',
                'customer_phone_1' => $this->phonePrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'customer_phone_2' => '',
                'governorate' => 'القاهرة',
                'city' => 'القاهرة',
                'address' => 'عنوان',
                'order_date' => now()->toDateString(),
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

            OrderDetails::create(['order_id' => $order->id]);

            return $order;
        });
    }
}
