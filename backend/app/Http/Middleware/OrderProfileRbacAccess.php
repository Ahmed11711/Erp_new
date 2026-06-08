<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** تعريفات في config/order_rbac_profiles.php */
class OrderProfileRbacAccess
{
    public function handle(Request $request, Closure $next, string $profile): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $profiles = config('order_rbac_profiles', []);
        if (! isset($profiles[$profile])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cfg = $profiles[$profile];
        $departments = $cfg['departments'] ?? [];
        $permissions = $cfg['permissions'] ?? [];

        if (RbacLegacyAccess::passes($user, $departments, $permissions)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
