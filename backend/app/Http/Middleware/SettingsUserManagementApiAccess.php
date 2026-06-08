<?php

namespace App\Http\Middleware;

use App\Support\RbacLegacyAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** إعدادات النظام: إضافة مستخدم + قائمة الأدوار — قسم Admin قديماً أو أي من settings/view/edit أو إدارة RBAC */
final class SettingsUserManagementApiAccess
{
    private const LEGACY_DEPARTMENTS = ['Admin'];

    /** يطابق manage-system rbacPermissions للمسارات الأساسية */
    private const RBAC_PERMISSIONS = ['settings.view', 'settings.edit', 'system.rbac'];

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
