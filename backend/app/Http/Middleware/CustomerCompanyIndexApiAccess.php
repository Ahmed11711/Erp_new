<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * قائمة العملاء (GET companies) لشاشات المحاسبة مثل سند القبض — يجب أن تتوافق مع finance.view.
 */
class CustomerCompanyIndexApiAccess
{
    /** مطابق لـ order_rbac_profiles.companies_main.departments */
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Operation Management',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
        'Data Entry',
    ];

    private const RBAC_PERMISSIONS = [
        'orders.view',
        'finance.edit',
        'finance.view',
        'customer_companies.view',
        'customer_companies.manage',
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
