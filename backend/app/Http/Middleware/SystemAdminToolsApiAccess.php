<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** شاشات admin مثل التتبع — قسم Admin قديماً أو system.rbac (يطابق RBAC_ROUTE.systemAdmin) */
final class SystemAdminToolsApiAccess
{
    private const LEGACY_DEPARTMENTS = ['Admin'];

    private const RBAC_PERMISSIONS = ['system.rbac'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (RbacLegacyAccess::passes($user, self::LEGACY_DEPARTMENTS, self::RBAC_PERMISSIONS)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
