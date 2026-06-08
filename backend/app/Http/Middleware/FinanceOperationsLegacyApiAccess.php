<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** بنوك، مصروفات، أصول، مستودعات استيراد، إلخ — القراءة: finance.view؛ التعديل: finance.edit */
class FinanceOperationsLegacyApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    private const READ_PERMISSIONS = ['finance.view', 'finance.edit', 'system.rbac'];

    private const WRITE_PERMISSIONS = ['finance.edit', 'system.rbac'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $method = strtoupper($request->method());
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        $perms = $isRead ? self::READ_PERMISSIONS : self::WRITE_PERMISSIONS;

        if (RbacLegacyAccess::passes($user, self::LEGACY_DEPARTMENTS, $perms)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
