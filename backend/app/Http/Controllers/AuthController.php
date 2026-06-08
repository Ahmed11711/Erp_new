<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
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
        $this->middleware('auth:api', ['except' => ['login']]);
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
        $token =auth()->claims([
            'user' => auth()->user(),
        ])->attempt($credentials);
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

    public function destroy($id)
    {
        $user = User::find($id);
        if(!$user){
        return response()->json(['error' => 'Not Found'], 404);
        }
        $user->delete();
        return response()->json('deleted sucuessfully');
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
        return $this->respondWithToken(auth()->refresh());
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
            'expires_in' => auth()->factory()->getTTL(),
        ]);
    }
}
