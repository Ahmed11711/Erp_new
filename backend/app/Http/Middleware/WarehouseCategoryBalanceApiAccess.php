<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** مسارات أرصدة المخازن وتفاصيل الأصناف حسب المخزن (مجموعة Finance السابقة الجزئية). */
class WarehouseCategoryBalanceApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    private const RBAC_PERMISSIONS = [
        'inventory.view',
        'inventory.transfer',
        'inventory.create',
        'inventory.edit',
        'categories.view',
        'categories.manage',
    ];

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
