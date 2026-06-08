<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeesHrApiAccess
{
    private const LEGACY_DEPARTMENTS = [
        'Admin',
        'Operation Management',
        'Financial Accounts',
        'Account Management',
        'Logistics Specialist',
    ];

    private const READ_PERMISSIONS = [
        'employees.view',
        'employees.create',
        'employees.edit',
        'employees.attendance',
        'employees.salary',
        'finance.edit',
    ];

    private const WRITE_PERMISSIONS = [
        'employees.create',
        'employees.edit',
        'employees.attendance',
        'employees.salary',
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
