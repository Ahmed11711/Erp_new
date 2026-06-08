<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\DepartmentRoleTemplate;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\Rbac\PermissionAuditLogger;
use App\Services\Rbac\PermissionResolutionService;
use App\Services\Rbac\RbacStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;

class RbacUserAccessController extends Controller
{
    public function __construct(
        protected PermissionResolutionService $resolver,
        protected PermissionAuditLogger $audit,
        protected RbacStampService $stamp
    ) {
    }

    public function matrix(User $user)
    {
        /** Same guard resolution as roles/permissions API — avoids empty matrix when config differs from Spatie */
        $guard = Guard::getDefaultName($user);
        $user->load(['roles.permissions']);

        $rolePermIds = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $p) {
                if ($p->guard_name === $guard) {
                    $rolePermIds[$p->id] = true;
                }
            }
        }

        $deptPermIds = [];
        $tpl = DepartmentRoleTemplate::query()
            ->where('department', $user->department)
            ->with(['role.permissions'])
            ->first();

        if ($tpl && $tpl->role) {
            foreach ($tpl->role->permissions as $p) {
                if ($p->guard_name === $guard) {
                    $deptPermIds[$p->id] = true;
                }
            }
        }

        $overrides = UserPermissionOverride::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('permission_id');

        $perms = Permission::query()
            ->where('guard_name', $guard)
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        $grouped = [];
        foreach ($perms->groupBy(fn ($p) => $p->module ?: 'general') as $module => $list) {
            $grouped[$module] = $list->map(function (Permission $p) use ($rolePermIds, $deptPermIds, $overrides, $user) {
                $o = $overrides->get($p->id);
                $state = 'neutral';
                if ($o && $o->type === 'deny') {
                    $state = 'denied';
                } elseif ($o && $o->type === 'allow') {
                    $state = 'allowed_override';
                } elseif (isset($rolePermIds[$p->id])) {
                    $state = 'inherited_role';
                } elseif (isset($deptPermIds[$p->id])) {
                    $state = 'inherited_department';
                }

                $needle = $p->slug ?: Str::slug(str_replace(['.', '_'], ' ', $p->name), '.');
                $effective = $this->resolver->hasPermission($user, $needle !== '' ? $needle : $p->name);

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => strtolower((string) ($p->slug ?: '')),
                    'module' => $p->module,
                    'state' => $state,
                    'effective' => $effective,
                ];
            })->values();
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'department' => $user->department,
                'email' => $user->email,
            ],
            'assigned_role_ids' => $user->roles->pluck('id')->values()->all(),
            'department_template_role_id' => $tpl?->role_id,
            'groups' => $grouped,
            'snapshot' => $this->resolver->resolve($user),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.permission_id' => ['required_with:overrides', 'integer', 'exists:permissions,id'],
            'overrides.*.type' => ['required_with:overrides', Rule::in(['allow', 'deny'])],
            'update_department_template' => ['nullable', 'boolean'],
            'department_template_role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            if (array_key_exists('role_ids', $data)) {
                $user->syncRoles($data['role_ids'] ?? []);
            }

            if (array_key_exists('overrides', $data)) {
                UserPermissionOverride::query()->where('user_id', $user->id)->delete();
                foreach ($data['overrides'] ?? [] as $row) {
                    UserPermissionOverride::query()->create([
                        'user_id' => $user->id,
                        'permission_id' => $row['permission_id'],
                        'type' => $row['type'],
                    ]);
                }
            }

            if (! empty($data['update_department_template'])) {
                $rid = $data['department_template_role_id'] ?? $user->roles()->first()?->id;
                if ($rid) {
                    DepartmentRoleTemplate::query()->updateOrCreate(
                        ['department' => $user->department],
                        ['role_id' => $rid]
                    );
                }
            }
        });

        $this->stamp->bumpUser($user->id);

        $this->audit->log('user.access_updated', auth()->user(), $user, User::class, $user->id, [], $request);

        return response()->json([
            'ok' => true,
            'snapshot' => $this->resolver->resolve($user),
        ]);
    }
}
