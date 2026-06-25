<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** الوصفات + خطوط الإنتاج CRUD — مواءمة manufacturing.view والأصناف */
class RecipeManufacturingApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Data Entry',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    private const READ_PERMISSIONS = [
        'manufacturing.view',
        'categories.view',
        'categories.manage',
    ];

    private const WRITE_PERMISSIONS = [
        'manufacturing.edit_recipe',
        'categories.manage',
        'system.rbac',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $method = strtoupper($request->method());
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        $perms = $isRead ? self::READ_PERMISSIONS : self::WRITE_PERMISSIONS;
        $legacyDepts = $isRead
            ? array_merge(self::LEGACY_DEPARTMENTS, ['Customer Service'])
            : self::LEGACY_DEPARTMENTS;

        if (RbacLegacyAccess::passes($user, $legacyDepts, $perms)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
