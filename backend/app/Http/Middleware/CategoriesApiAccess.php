<?php

namespace App\Http\Middleware;

use App\Services\Rbac\PermissionResolutionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD وبحث الأصناف — مواءمة RBAC (categories.view / categories.manage) مع أقسام api.php القديمة.
 */
class CategoriesApiAccess
{
    /** قراءة + بحث (مجموعة Customer Service السابقة + Financial للقوائم) */
    private const READ_DEPARTMENTS = [
        'Admin',
        'Data Entry',
        'Account Management',
        'Logistics Specialist',
        'Customer Service',
        'Financial Accounts',
    ];

    /** تعديل وحذف وإنشاء (مجموعة Financial Accounts السابقة لمسارات الأصناف) */
    private const WRITE_DEPARTMENTS = [
        'Admin',
        'Data Entry',
        'Account Management',
        'Logistics Specialist',
        'Financial Accounts',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $method = strtoupper($request->method());
        $isRead = in_array($method, ['GET', 'HEAD'], true);

        $userDept = Str::lower(trim((string) ($user->department ?? '')));

        $resolver = app(PermissionResolutionService::class);

        if ($isRead) {
            foreach (self::READ_DEPARTMENTS as $allowed) {
                if (Str::lower(trim((string) $allowed)) === $userDept) {
                    return $next($request);
                }
            }

            if ($resolver->hasAnyPermission($user, ['categories.view', 'categories.manage'])) {
                return $next($request);
            }

            return response()->json(['message' => 'Forbidden'], 403);
        }

        foreach (self::WRITE_DEPARTMENTS as $allowed) {
            if (Str::lower(trim((string) $allowed)) === $userDept) {
                return $next($request);
            }
        }

        if ($resolver->hasAnyPermission($user, ['categories.manage'])) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
