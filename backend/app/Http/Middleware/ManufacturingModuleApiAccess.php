<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** مجموعة manufacture + أوامر الإنتاج تحت التصنيع في الواجهة */
class ManufacturingModuleApiAccess
{
    private const LEGACY_DEPARTMENTS = ['Admin', 'Financial Accounts'];

    private const RBAC_PERMISSIONS = ['manufacturing.view', 'categories.manage', 'system.rbac'];

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
