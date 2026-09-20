<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuppliersPurchasesApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    /** قراءة القوائم (سند قبض/صرف، تقارير) — يشمل من يملك finance.view فقط */
    private const READ_PERMISSIONS = [
        'suppliers.view',
        'purchases.view',
        'categories.manage',
        'finance.edit',
        'finance.view',
    ];

    /** تعديل الموردين/المشتريات بدون منح finance.view وحدها حق الكتابة */
    private const WRITE_PERMISSIONS = [
        'suppliers.view',
        'purchases.view',
        'categories.manage',
        'finance.edit',
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

        if (RbacLegacyAccess::passes($user, self::LEGACY_DEPARTMENTS, $perms)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
