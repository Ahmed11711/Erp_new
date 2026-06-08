<?php

namespace App\Services\Rbac;

use App\Models\DepartmentRoleTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PermissionResolutionService
{
    public function __construct(
        protected RbacStampService $stampService
    ) {}

    /**
     * @return array{
     *   effective_slugs: array<int, string>,
     *   effective_keys: array<int, string>,
     *   denied_slugs: array<int, string>,
     *   allowed_slugs: array<int, string>,
     *   roles: array<int, array{id:int,name:string,slug:?string}>,
     *   permission_objects: array<int, array{id:int,slug:string,name:string,module:?string}>
     * }
     */
    public function resolve(User $user): array
    {
        $key = 'rbac:resolved:v4:'.$user->id.':'.$this->stampService->stampFor($user->id);

        return Cache::remember($key, config('rbac.cache_ttl'), fn () => $this->compute($user));
    }

    /**
     * Flat list of permission slugs granted (modern checks).
     *
     * @return list<string>
     */
    public function effectiveSlugs(User $user): array
    {
        return $this->resolve($user)['effective_slugs'];
    }

    /**
     * Slugs + lowercase legacy names (backward compatible with existing Angular checks).
     *
     * @return list<string>
     */
    public function effectiveKeys(User $user): array
    {
        return $this->resolve($user)['effective_keys'];
    }

    public function hasPermission(User $user, string $needle): bool
    {
        $normalized = strtolower(trim($needle));
        $keys = array_flip($this->effectiveKeys($user));

        return isset($keys[$normalized]);
    }

    public function hasAnyPermission(User $user, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($this->hasPermission($user, (string) $n)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(User $user, array $needles): bool
    {
        foreach ($needles as $n) {
            if (! $this->hasPermission($user, (string) $n)) {
                return false;
            }
        }

        return true;
    }

    /**
     * تعريف «سوبر أدمن» للعرض أو منطق مستقبلي؛ الصلاحيات الفعلية تُحسب من أدوار المستخدم وقالب القسم وتجاوزاته فقط.
     */
    public function isSuperAdmin(User $user): bool
    {
        $emails = config('rbac.super_admin_emails', []);
        if ($emails !== [] && in_array(strtolower((string) $user->email), $emails, true)) {
            return true;
        }

        $slug = config('rbac.super_admin_role_slug', 'super-admin');
        $user->loadMissing('roles');

        foreach ($user->roles as $role) {
            if ($role->slug === $slug || strtolower((string) $role->name) === 'super admin') {
                return true;
            }
        }

        return false;
    }

    protected function compute(User $user): array
    {
        $guard = config('auth.defaults.guard');

        $user->loadMissing(['roles.permissions']);

        $overrideRows = UserPermissionOverride::query()
            ->where('user_id', $user->id)
            ->with(['permission:id,name,slug,module,guard_name'])
            ->get();

        $denyIds = [];
        $allowIds = [];
        foreach ($overrideRows as $row) {
            if (! $row->permission || $row->permission->guard_name !== $guard) {
                continue;
            }
            if ($row->type === 'deny') {
                $denyIds[$row->permission_id] = true;
            } else {
                $allowIds[$row->permission_id] = true;
            }
        }

        $grantedIds = [];

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->guard_name === $guard) {
                    $grantedIds[$permission->id] = true;
                }
            }
        }

        $template = DepartmentRoleTemplate::query()
            ->where('department', $user->department)
            ->with(['role.permissions'])
            ->first();

        if ($template && $template->role) {
            foreach ($template->role->permissions as $permission) {
                if ($permission->guard_name === $guard) {
                    $grantedIds[$permission->id] = true;
                }
            }
        }

        foreach (array_keys($allowIds) as $permId) {
            $grantedIds[(int) $permId] = true;
        }

        foreach (array_keys($denyIds) as $permId) {
            unset($grantedIds[(int) $permId]);
        }

        $effectiveIds = array_keys($grantedIds);

        /** ERP convention: «Admin» department users without role/template grants keep legacy full menus via preset bundle. */
        if ($effectiveIds === [] && trim((string) ($user->department ?? '')) === 'Admin') {
            $preset = Role::query()
                ->where('slug', 'admin-preset')
                ->where('guard_name', $guard)
                ->first();

            if ($preset) {
                $preset->load('permissions');
                foreach ($preset->permissions as $permission) {
                    if ($permission->guard_name === $guard) {
                        $grantedIds[$permission->id] = true;
                    }
                }
                foreach (array_keys($allowIds) as $permId) {
                    $grantedIds[(int) $permId] = true;
                }
                foreach (array_keys($denyIds) as $permId) {
                    unset($grantedIds[(int) $permId]);
                }
                $effectiveIds = array_keys($grantedIds);
            }
        }

        return $this->buildResolvedPayload($user, $effectiveIds, $denyIds, $allowIds, $guard);
    }

    /**
     * @param  array<int, true>  $denyIds
     * @param  array<int, true>  $allowIds
     * @param  list<int>  $effectiveIds
     */
    protected function buildResolvedPayload(User $user, array $effectiveIds, array $denyIds, array $allowIds, string $guard): array
    {
        if ($effectiveIds === []) {
            return [
                'effective_slugs' => [],
                'effective_keys' => [],
                'denied_slugs' => $this->slugListForPermissionIds(array_keys($denyIds), $guard),
                'allowed_slugs' => $this->slugListForPermissionIds(array_keys($allowIds), $guard),
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'slug' => $r->slug,
                ])->values()->all(),
                'permission_objects' => [],
            ];
        }

        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('id', $effectiveIds)
            ->get(['id', 'name', 'slug', 'module']);

        $effectiveSlugs = [];
        $effectiveKeysMap = [];
        $permissionObjects = [];

        foreach ($permissions as $perm) {
            $slug = $this->normalizeSlug($perm);
            if ($slug === '') {
                continue;
            }
            $effectiveSlugs[] = $slug;
            $effectiveKeysMap[$slug] = true;
            $legacy = strtolower(trim((string) $perm->name));
            if ($legacy !== '') {
                $effectiveKeysMap[$legacy] = true;
            }
            $permissionObjects[] = [
                'id' => $perm->id,
                'slug' => $slug,
                'name' => $perm->name,
                'module' => $perm->module,
            ];
        }

        $effectiveSlugs = array_values(array_unique($effectiveSlugs));

        return [
            'effective_slugs' => $effectiveSlugs,
            'effective_keys' => array_keys($effectiveKeysMap),
            'denied_slugs' => $this->slugListForPermissionIds(array_keys($denyIds), $guard),
            'allowed_slugs' => $this->slugListForPermissionIds(array_keys($allowIds), $guard),
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ])->values()->all(),
            'permission_objects' => $permissionObjects,
        ];
    }

    protected function normalizeSlug(Permission $permission): string
    {
        return strtolower((string) ($permission->slug
            ?: Str::slug(str_replace(['.', '_'], ' ', $permission->name), '.')));
    }

    /**
     * @param  array<int, int>  $ids
     * @return list<string>
     */
    protected function slugListForPermissionIds(array $ids, string $guard): array
    {
        if ($ids === []) {
            return [];
        }

        return Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Permission $p) => $this->normalizeSlug($p))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
