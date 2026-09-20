<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use App\Services\SystemLock\SystemLockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemLockServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_default_state_is_unlocked(): void
    {
        Setting::query()->where('key', SystemLockService::SETTING_KEY)->delete();
        $user = User::factory()->create(['department' => 'Test']);
        $service = app(SystemLockService::class);

        $this->assertFalse($service->isLocked());
        $this->assertFalse($service->isRestricted($user));
        $this->assertSame(SystemLockService::DEFAULT_MESSAGE, $service->state()['message']);
    }

    public function test_update_always_exempts_the_operator(): void
    {
        Setting::query()->where('key', SystemLockService::SETTING_KEY)->delete();
        $operator = User::factory()->create(['department' => 'Admin', 'name' => 'Operator']);
        $service = $this->serviceThatTreatsUserAsOperator($operator);

        $payload = $service->update($operator, true, 'صيانة', []);

        $this->assertTrue($payload['locked']);
        $this->assertContains((int) $operator->id, $payload['exempt_user_ids']);
        $this->assertFalse($payload['restricted']);
        $this->assertSame('صيانة', $payload['message']);
        $this->assertTrue($payload['show_message']);

        $hidden = $service->update($operator, true, 'صيانة', [], false);
        $this->assertFalse($hidden['show_message']);
    }

    private function serviceThatTreatsUserAsOperator(User $operator): SystemLockService
    {
        $resolver = $this->createMock(PermissionResolutionService::class);
        $resolver->method('isSuperAdmin')->willReturn(false);
        $resolver->method('hasPermission')->willReturnCallback(
            function (User $user, string $slug) use ($operator) {
                return (int) $user->id === (int) $operator->id
                    && in_array($slug, [
                        SystemLockService::CONTROL_PERMISSION,
                        SystemLockService::UNLOCK_PERMISSION,
                    ], true);
            }
        );

        return new SystemLockService($resolver);
    }
}
