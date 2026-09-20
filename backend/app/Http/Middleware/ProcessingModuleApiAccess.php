<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessingModuleApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    private const RBAC_PERMISSIONS = [
        'processing.view',
        'purchases.view',
        'suppliers.view',
        'finance.view',
        'system.rbac',
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
