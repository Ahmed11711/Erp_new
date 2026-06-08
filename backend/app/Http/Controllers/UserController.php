<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\Rbac\RbacStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with([
                'roles:id,name,slug',
                'permissions:id,name,slug',
            ])
            ->get();

        return response()->json($users, 200);
    }

    protected function compactUsersForPicker()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department']);
    }

    /**
     * Minimal user rows for WhatsApp number assignment UI (matches Angular assignWhatsAppNumbersGuard RBAC).
     */
    public function whatsappAssignmentPicker()
    {
        return response()->json(['data' => $this->compactUsersForPicker()], 200);
    }

    /** قائمة مستخدمين بسيطة للتتبع وغيرها — لا تُرجع أدواراً / صلاحيات كاملة */
    public function compactUserDirectory()
    {
        return response()->json(['data' => $this->compactUsersForPicker()], 200);
    }

    public function usersForNotification()
    {
        $data = User::whereNotIn('id', [auth()->id()])->whereNot('department', 'Employee')->whereNot('department', 'test')->get();

        return response()->json($data, 200);
    }

    public function user_permission($id)
    {
        $user = User::findOrFail($id);

        return response()->json($user->resolvedPermissionKeys(), 200);
    }

    public function create_permssion(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:190',
            'module' => 'nullable|string|max:190',
        ]);

        $guard = config('auth.defaults.guard');
        $slug = $request->slug ?: Str::slug(str_replace(['.', '_'], ' ', $request->name), '.');
        if ($slug === '') {
            $slug = 'permission.'.uniqid();
        }

        $permission = Permission::query()->create([
            'name' => $request->name,
            'slug' => strtolower($slug),
            'module' => $request->module ?? explode('.', $slug)[0],
            'guard_name' => $guard,
        ]);

        return response()->json($permission, 200);
    }

    public function give_permission(Request $request, $id)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        $perm = $this->resolvePermissionInput($request->input('permission'));
        if (! $perm) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        UserPermissionOverride::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'permission_id' => $perm->id,
            ],
            ['type' => 'allow']
        );

        app(RbacStampService::class)->bumpUser($user->id);

        return response()->json(['ok' => true], 200);
    }

    public function get_all_permssions()
    {
        $permissions = Permission::query()
            ->select(['id', 'name', 'slug', 'module'])
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return response()->json($permissions, 200);
    }

    public function revoke_permssion(Request $request, $id)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = User::findOrFail($id);
        $perm = $this->resolvePermissionInput($request->input('permission'));
        if (! $perm) {
            return response()->json(['message' => 'Permission not found'], 404);
        }

        UserPermissionOverride::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'permission_id' => $perm->id,
            ],
            ['type' => 'deny']
        );

        app(RbacStampService::class)->bumpUser($user->id);

        return response()->json(['ok' => true], 200);
    }

    protected function resolvePermissionInput(string $raw): ?Permission
    {
        $raw = trim($raw);
        $guard = config('auth.defaults.guard');

        return Permission::query()
            ->where('guard_name', $guard)
            ->where(function ($q) use ($raw) {
                $q->where('name', $raw)
                    ->orWhere('slug', $raw)
                    ->orWhere('slug', strtolower($raw));
            })
            ->first();
    }
}
