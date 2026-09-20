<?php

namespace App\Support;

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

/**
 * السماح للمستخدم إذا كان ضمن أحد الأقسام القديمة أو يملك أيّاً من صلاحيات RBAC.
 */
final class RbacLegacyAccess
{
    /**
     * @param  list<string>  $legacyDepartments
     * @param  list<string>  $permissionSlugs
     */
    public static function passes(?Authenticatable $user, array $legacyDepartments, array $permissionSlugs): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $userDept = Str::lower(trim((string) ($user->department ?? '')));

        foreach ($legacyDepartments as $allowed) {
            if (Str::lower(trim((string) $allowed)) === $userDept) {
                return true;
            }
        }

        if ($permissionSlugs === []) {
            return false;
        }

        return app(PermissionResolutionService::class)->hasAnyPermission($user, $permissionSlugs);
    }
}
