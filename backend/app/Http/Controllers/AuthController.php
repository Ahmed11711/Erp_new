<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use App\Services\SystemLock\SystemLockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // refresh must stay public: jwt-auth can rotate an expired access token
        // within refresh_ttl. auth:api would reject that token before this action runs.
        $this->middleware('auth:api', ['except' => ['login', 'refresh']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validation = Validator::make(request()->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if($validation->fails()){
            return response()->json(['message'=>$validation->errors()], 422);
        }
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            $error['error']=['email or password inncorrect'];
            return response()->json(['message'=>$error], 401);
        }
        return $this->respondWithToken($token);
    }

    /**
     * Admin creates another user (JWT stays on the admin — does not log in as the new user).
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'department' => ['required', 'string', 'max:191'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Hash::make($request->password),
            'department' => $request->department,
        ]);

        $roleIds = array_values(array_filter($request->input('role_ids', [])));
        if ($roleIds !== []) {
            $user->syncRoles($roleIds);
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpUser($user->id);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->fresh(['roles:id,name,slug']),
        ], 201);
    }

    public function getUsers()
    {
        $user = User::get();
        return response()->json($user,200);
    }

    /**
     * حذف المستخدم نهائياً من جدول users فقط (Hard Delete).
     * لا يحذف القيود/الطلبات/الحركات المرتبطة — يُفصل فقط ارتباط الصلاحيات حتى لا يُمنع الحذف.
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'المستخدم غير موجود', 'deleted' => false], 404);
        }

        if ((int) auth()->id() === (int) $user->id) {
            return response()->json(['message' => 'لا يمكن حذف حسابك الحالي وأنت مسجّل الدخول.', 'deleted' => false], 422);
        }

        $userId = (int) $user->id;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $userId) {
                // فصل صلاحيات الدخول فقط (ليست بيانات تشغيلية)
                if (method_exists($user, 'roles')) {
                    $user->roles()->detach();
                }
                if (method_exists($user, 'permissions')) {
                    $user->permissions()->detach();
                }
                \App\Models\UserPermissionOverride::query()->where('user_id', $userId)->delete();

                if (\Illuminate\Support\Facades\Schema::hasTable('bank_user')) {
                    \Illuminate\Support\Facades\DB::table('bank_user')->where('user_id', $userId)->delete();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('whatsapp_assignments')) {
                    \Illuminate\Support\Facades\DB::table('whatsapp_assignments')->where('user_id', $userId)->delete();
                }

                // حذف صف المستخدم فقط بدون تشغيل ON DELETE CASCADE على باقي الجداول
                \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
                try {
                    \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->delete();
                } finally {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }

                if (User::query()->whereKey($userId)->exists()) {
                    throw new \RuntimeException('المستخدم ما زال موجوداً في جدول users.');
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'فشل حذف المستخدم',
                'deleted' => false,
            ], 500);
        }

        return response()->json([
            'message' => 'تم حذف المستخدم من جدول المستخدمين نهائياً، بدون حذف البيانات المرتبطة به.',
            'deleted' => true,
            'id' => $userId,
            'legacy' => 'deleted sucuessfully',
        ], 200);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        /** @var User $user */
        $user = auth()->user();
        $resolver = app(PermissionResolutionService::class);
        $resolved = $resolver->resolve($user);

        return response()->json(array_merge(
            $user->only(['id', 'name', 'email', 'department', 'whatsapp_phone_number_id', 'role']),
            [
                'rbac' => [
                    'roles' => $resolved['roles'],
                    'permissions' => $resolved['effective_slugs'],
                    'allowed_permissions' => $resolved['allowed_slugs'],
                    'denied_permissions' => $resolved['denied_slugs'],
                    'effective_permission_keys' => $resolved['effective_keys'],
                ],
                'system_lock' => app(SystemLockService::class)->statusPayload($user),
            ]
        ));
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            $token = auth()->refresh();
            auth()->setToken($token);
            if (! auth()->user()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return $this->respondWithToken($token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        /** @var User $user */
        $user = auth()->user();
        $resolver = app(PermissionResolutionService::class);
        $resolved = $resolver->resolve($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => $user->department,
            'name' => $user->name,
            'permissions' => $resolved['effective_keys'],
            'rbac' => [
                'roles' => $resolved['roles'],
                'permissions' => $resolved['effective_slugs'],
                'allowed_permissions' => $resolved['allowed_slugs'],
                'denied_permissions' => $resolved['denied_slugs'],
                'effective_permission_keys' => $resolved['effective_keys'],
            ],
            'system_lock' => app(SystemLockService::class)->statusPayload($user),
            'expires_in' => auth()->factory()->getTTL(),
        ]);
    }
}
