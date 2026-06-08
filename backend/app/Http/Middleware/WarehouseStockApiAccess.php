<?php

namespace App\Http\Middleware;

use App\Services\Rbac\PermissionResolutionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * يسمح بـ API المخزون (stocks) إما بالأقسام التقليدية أو بصلاحيات المخزون في RBAC.
 */
class WarehouseStockApiAccess
{
    private const DEPARTMENTS = [
        'Admin',
        'Data Entry',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    private const PERMISSIONS = [
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

        $userDept = Str::lower(trim((string) ($user->department ?? '')));

        foreach (self::DEPARTMENTS as $allowed) {
            if (Str::lower(trim((string) $allowed)) === $userDept) {
                return $next($request);
            }
        }

        if (app(PermissionResolutionService::class)->hasAnyPermission($user, self::PERMISSIONS)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
