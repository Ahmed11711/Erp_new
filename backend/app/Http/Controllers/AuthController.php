<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
 use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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

    public function register()
    {
        $credentials = request(['name', 'email', 'password', 'department']);
        $credentials['password'] = \Hash::make($credentials['password']);
        $user = User::create($credentials);
        $token = auth()->login($user);
        return $this->respondWithToken($token);
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
        return response()->json(auth()->user());
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
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user'=>auth()->user()->department,
            'name'=>auth()->user()->name,
            'permissions'=>auth()->user()->getAllPermissions()->pluck('name'),
            'expires_in' => auth()->factory()->getTTL(),
            // 'department' => auth()->payload()->get('department'),
        ]);
    }
}
