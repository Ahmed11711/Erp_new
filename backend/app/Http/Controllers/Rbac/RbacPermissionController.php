<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PermissionDependency;
use App\Services\Rbac\PermissionAuditLogger;
use App\Services\Rbac\PermissionDependencyValidator;
use App\Services\Rbac\RbacStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;

class RbacPermissionController extends Controller
{
    public function __construct(
        protected PermissionAuditLogger $audit,
        protected PermissionDependencyValidator $deps,
        protected RbacStampService $stamp
    ) {
    }

    public function index(Request $request)
    {
        $guard = $request->query('guard', auth()->check() ? Guard::getDefaultName(auth()->user()) : config('auth.defaults.guard'));

        $permissions = Permission::query()
            ->where('guard_name', $guard)
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'module', 'description', 'guard_name']);

        return response()->json([
            'guard_name' => $guard,
            'grouped' => $permissions->groupBy(fn ($p) => $p->module ?: 'general')->map->values(),
            'flat' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $guard = $request->input('guard_name', auth()->check() ? Guard::getDefaultName(auth()->user()) : config('auth.defaults.guard'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('permissions', 'slug')->where(fn ($q) => $q->where('guard_name', $guard))],
            'module' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'requires_permission_ids' => ['nullable', 'array'],
            'requires_permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = $data['slug'] ?? Str::slug(str_replace(['.', '_'], ' ', $data['name']), '.');
        if ($slug === '') {
            $slug = 'permission.'.uniqid();
        }

        $permission = Permission::query()->create([
            'name' => $data['name'],
            'slug' => strtolower($slug),
            'module' => $data['module'] ?? explode('.', $slug)[0],
            'description' => $data['description'] ?? null,
            'guard_name' => $guard,
        ]);

        foreach ($data['requires_permission_ids'] ?? [] as $rid) {
            PermissionDependency::query()->firstOrCreate([
                'permission_id' => $permission->id,
                'requires_permission_id' => $rid,
            ]);
        }

        $this->audit->log('permission.created', auth()->user(), null, Permission::class, $permission->id, ['slug' => $permission->slug], $request);

        return response()->json($permission, 201);
    }

    public function update(Request $request, Permission $permission)
    {
        $guard = $permission->guard_name;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:190', Rule::unique('permissions', 'slug')->where(fn ($q) => $q->where('guard_name', $guard))->ignore($permission->id)],
            'module' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'requires_permission_ids' => ['nullable', 'array'],
            'requires_permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        if (isset($data['slug'])) {
            $data['slug'] = strtolower($data['slug']);
        }

        $permission->fill($data);
        $permission->save();

        if ($request->has('requires_permission_ids')) {
            PermissionDependency::query()->where('permission_id', $permission->id)->delete();
            foreach ($request->input('requires_permission_ids', []) as $rid) {
                PermissionDependency::query()->create([
                    'permission_id' => $permission->id,
                    'requires_permission_id' => $rid,
                ]);
            }
        }

        $this->stamp->bump();
        $this->audit->log('permission.updated', auth()->user(), null, Permission::class, $permission->id, [], $request);

        return response()->json($permission->fresh());
    }

    public function destroy(Permission $permission)
    {
        $this->audit->log('permission.deleted', auth()->user(), null, Permission::class, $permission->id, ['slug' => $permission->slug]);
        $permission->delete();

        return response()->json(['ok' => true]);
    }

    public function bulkAssignRoles(Request $request)
    {
        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'mode' => ['nullable', Rule::in(['sync_merge', 'attach'])],
        ]);

        $missing = $this->deps->missingRequirements($data['permission_ids']);
        if ($missing->isNotEmpty()) {
            return response()->json(['message' => 'Missing dependency permissions', 'missing' => $missing], 422);
        }

        $roles = \App\Models\Role::query()->whereIn('id', $data['role_ids'])->get();

        DB::transaction(function () use ($roles, $data): void {
            foreach ($roles as $role) {
                if (($data['mode'] ?? 'attach') === 'sync_merge') {
                    $existing = $role->permissions()->pluck('permissions.id')->all();
                    $merged = array_values(array_unique(array_merge($existing, $data['permission_ids'])));
                    $role->syncPermissions($merged);
                } else {
                    $role->givePermissionTo($data['permission_ids']);
                }
            }
        });

        $this->stamp->bumpAfterRolePermissionsChanged();
        $this->audit->log('permission.bulk_roles', auth()->user(), null, null, null, $data, $request);

        return response()->json(['ok' => true]);
    }
}
