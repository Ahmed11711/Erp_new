<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Rbac\RbacStampService;
use App\Services\SystemLock\SystemLockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemLockTest extends TestCase
{
    use DatabaseTransactions;

    private string $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = (string) config('auth.defaults.guard');
        Setting::query()->where('key', SystemLockService::SETTING_KEY)->delete();
    }

    public function test_operator_can_lock_and_restricted_user_is_blocked(): void
    {
        $operator = $this->makeUserWithPermissions(['system.lock']);
        $regular = User::factory()->create(['department' => 'Customer Service', 'name' => 'Locked User']);

        $lock = $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => true,
            'message' => 'عطل مؤقت للتجربة',
            'exempt_user_ids' => [],
        ]);

        $lock->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('can_lock', true)
            ->assertJsonPath('can_unlock', false)
            ->assertJsonPath('can_control', true)
            ->assertJsonPath('restricted', false)
            ->assertJsonPath('show_message', true);

        $blocked = $this->actingAs($regular, 'api')->getJson('/api/notification');
        $blocked->assertStatus(503)
            ->assertJsonPath('code', 'SYSTEM_LOCKED')
            ->assertJsonPath('message', 'عطل مؤقت للتجربة');

        $status = $this->actingAs($regular, 'api')->getJson('/api/system-lock');
        $status->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('restricted', true)
            ->assertJsonPath('can_control', false);
    }

    public function test_exempt_user_keeps_access_while_system_is_locked(): void
    {
        $operator = $this->makeUserWithPermissions(['system.lock']);
        $exempt = User::factory()->create(['department' => 'Warehouse', 'name' => 'Exempt User']);
        $regular = User::factory()->create(['department' => 'Warehouse', 'name' => 'Other User']);

        $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => true,
            'exempt_user_ids' => [$exempt->id],
        ])->assertOk();

        $this->actingAs($exempt, 'api')->getJson('/api/system-lock')
            ->assertOk()
            ->assertJsonPath('restricted', false);

        $this->actingAs($regular, 'api')->getJson('/api/notification')
            ->assertStatus(503)
            ->assertJsonPath('code', 'SYSTEM_LOCKED');
    }

    public function test_bypass_permission_keeps_access_without_lock_button(): void
    {
        $operator = $this->makeUserWithPermissions(['system.lock']);
        $bypass = $this->makeUserWithPermissions(['system.lock_bypass']);

        $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => true,
        ])->assertOk();

        $this->actingAs($bypass, 'api')->getJson('/api/system-lock')
            ->assertOk()
            ->assertJsonPath('locked', true)
            ->assertJsonPath('restricted', false)
            ->assertJsonPath('can_control', false);

        $forbidden = $this->actingAs($bypass, 'api')->putJson('/api/system-lock', [
            'locked' => false,
        ]);
        $forbidden->assertStatus(403);
    }

    public function test_lock_operator_cannot_restart_the_system(): void
    {
        $operator = $this->makeUserWithPermissions(['system.lock']);

        $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => true,
        ])->assertOk();

        $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => false,
        ])->assertStatus(403)->assertJsonPath('message', 'غير مسموح بإعادة تشغيل النظام.');
    }

    public function test_regular_user_cannot_toggle_lock(): void
    {
        $regular = User::factory()->create(['department' => 'Test']);

        $this->actingAs($regular, 'api')->putJson('/api/system-lock', [
            'locked' => true,
        ])->assertStatus(403);
    }

    public function test_unlock_permission_can_restart_the_system(): void
    {
        $locker = $this->makeUserWithPermissions(['system.lock']);
        $admin = $this->makeUserWithPermissions(['system.unlock']);
        $regular = User::factory()->create(['department' => 'Test']);

        $this->actingAs($locker, 'api')->putJson('/api/system-lock', ['locked' => true])->assertOk();
        $this->actingAs($admin, 'api')->putJson('/api/system-lock', ['locked' => false])->assertOk()
            ->assertJsonPath('locked', false)
            ->assertJsonPath('can_unlock', true);

        $this->actingAs($regular, 'api')->getJson('/api/system-lock')
            ->assertOk()
            ->assertJsonPath('locked', false)
            ->assertJsonPath('restricted', false);
    }

    public function test_restricted_user_can_load_last_ten_orders_preview(): void
    {
        $operator = $this->makeUserWithPermissions(['system.lock']);
        $regular = User::factory()->create(['department' => 'Customer Service', 'name' => 'Locked User']);

        $this->actingAs($operator, 'api')->putJson('/api/system-lock', [
            'locked' => true,
        ])->assertOk();

        $this->actingAs($regular, 'api')->getJson('/api/notification')
            ->assertStatus(503)
            ->assertJsonPath('code', 'SYSTEM_LOCKED');

        $this->actingAs($regular, 'api')->getJson('/api/orders/search')
            ->assertStatus(503)
            ->assertJsonPath('code', 'SYSTEM_LOCKED');

        $preview = $this->actingAs($regular, 'api')->getJson('/api/system-lock/orders-preview');
        $preview->assertOk()
            ->assertJsonStructure([
                'data',
                'total',
                'per_page',
                'lookups' => [
                    'companies',
                    'order_sources',
                    'shipping_ways',
                    'shipping_lines',
                ],
            ]);

        $this->assertLessThanOrEqual(10, count($preview->json('data') ?? []));
        $this->assertSame(10, (int) $preview->json('per_page'));
        $this->assertLessThanOrEqual(10, (int) $preview->json('total'));
    }

    /**
     * @param  list<string>  $slugs
     */
    private function makeUserWithPermissions(array $slugs): User
    {
        $perms = [];
        foreach ($slugs as $slug) {
            $perms[] = Permission::query()->firstOrCreate(
                ['slug' => $slug, 'guard_name' => $this->guard],
                [
                    'name' => $slug,
                    'module' => 'system',
                ]
            );
        }

        $role = Role::query()->create([
            'name' => 'system-lock-test-'.uniqid(),
            'slug' => 'system-lock-test-'.uniqid(),
            'guard_name' => $this->guard,
        ]);
        $role->syncPermissions(collect($perms)->pluck('id')->all());
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create(['department' => 'Lock Test']);
        $user->roles()->sync([$role->id]);
        app(RbacStampService::class)->bumpUser($user->id);

        return $user->fresh();
    }
}
