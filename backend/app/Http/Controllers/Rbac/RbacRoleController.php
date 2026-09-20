<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\PermissionAuditLogger;
use App\Services\Rbac\PermissionDependencyValidator;
use App\Services\Rbac\RbacStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Guard;

class RbacRoleController extends Controller
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

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->withCount('permissions')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'guard_name']);

        return response()->json(['guard_name' => $guard, 'roles' => $roles]);
    }

    public function show(Role $role)
    {
        $role->load(['permissions' => fn ($q) => $q->orderBy('module')->orderBy('name')]);

        return response()->json($role);
    }

    public function store(Request $request)
    {
        $guard = $request->input('guard_name', auth()->check() ? Guard::getDefaultName(auth()->user()) : config('auth.defaults.guard'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('guard_name', $guard))],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name'], '-');
        if ($slug === '') {
            $slug = 'role-'.uniqid();
        }

        $role = Role::query()->create([
            'name' => $data['name'],
            'slug' => strtolower($slug),
            'description' => $data['description'] ?? null,
            'guard_name' => $guard,
        ]);

        $permIds = $data['permission_ids'] ?? [];
        if ($permIds !== []) {
            $missing = $this->deps->missingRequirements($permIds);
            if ($missing->isNotEmpty()) {
                $role->delete();

                return response()->json(['message' => 'Missing dependency permissions', 'missing' => $missing], 422);
            }
            $role->syncPermissions($permIds);
            $this->stamp->bumpAfterRolePermissionsChanged();
        }

        $this->audit->log('role.created', auth()->user(), null, Role::class, $role->id, ['slug' => $role->slug], $request);

        return response()->json($role->loadCount('permissions'), 201);
    }

    public function update(Request $request, Role $role)
    {
        $guard = $role->guard_name;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:190', Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('guard_name', $guard))->ignore($role->id)],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($data['slug'])) {
            $data['slug'] = strtolower($data['slug']);
        }

        $role->fill($data);
        $role->save();

        $this->audit->log('role.updated', auth()->user(), null, Role::class, $role->id, [], $request);

        return response()->json($role->fresh()->loadCount('permissions'));
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $missing = $this->deps->missingRequirements($data['permission_ids']);
        if ($missing->isNotEmpty()) {
            return response()->json(['message' => 'Missing dependency permissions', 'missing' => $missing], 422);
        }

        $role->syncPermissions($data['permission_ids']);
        $this->stamp->bumpAfterRolePermissionsChanged();

        $this->audit->log('role.permissions_sync', auth()->user(), null, Role::class, $role->id, ['count' => count($data['permission_ids'])], $request);

        return response()->json(['ok' => true, 'permissions_count' => $role->permissions()->count()]);
    }

    public function clone(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:190'],
        ]);

        $guard = $role->guard_name;
        $slug = $data['slug'] ?? Str::slug($data['name'], '-');
        $slug = strtolower($slug ?: 'role-'.uniqid());

        $exists = Role::query()->where('guard_name', $guard)->where('slug', $slug)->exists();
        if ($exists) {
            $slug .= '-'.substr(uniqid(), -4);
        }

        $copy = Role::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $role->description,
            'guard_name' => $guard,
        ]);

        $copy->syncPermissions($role->permissions->pluck('id')->all());
        $this->stamp->bumpAfterRolePermissionsChanged();

        $this->audit->log('role.cloned', auth()->user(), null, Role::class, $copy->id, ['from_role_id' => $role->id], $request);

        return response()->json($copy->load(['permissions'])->loadCount('permissions'), 201);
    }

    public function destroy(Role $role)
    {
        $this->audit->log('role.deleted', auth()->user(), null, Role::class, $role->id, ['slug' => $role->slug]);
        $role->delete();

        return response()->json(['ok' => true]);
    }
}
