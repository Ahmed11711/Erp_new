<?php

namespace App\Services\Rbac;

use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class RbacStampService
{
    /**
     * بعد تغيير pivot الدور↔الصلاحيات؛ يصفّر أيضاً كاش Spatie حتى لا تُقرأ علاقات قديمة.
     */
    public function bumpAfterRolePermissionsChanged(): void
    {
        $this->bump();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function bump(): void
    {
        $next = (int) Cache::get('rbac:global_stamp', 1) + 1;
        Cache::forever('rbac:global_stamp', $next);
    }

    public function bumpUser(int $userId): void
    {
        $next = (int) Cache::get('rbac:user_stamp:'.$userId, 1) + 1;
        Cache::forever('rbac:user_stamp:'.$userId, $next);
    }

    public function stampFor(int $userId): string
    {
        return ((int) Cache::get('rbac:global_stamp', 1)).':'.((int) Cache::get('rbac:user_stamp:'.$userId, 1));
    }
}
