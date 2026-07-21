<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * صلاحية حذف أوامر التشغيل الخارجي (مع عكس المخزون والقيود) — للأدمن.
 *
 * php artisan db:seed --class=ProcessingDeleteOrderPermissionSeeder
 */
class ProcessingDeleteOrderPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'processing.delete_order';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Delete External Processing Orders',
                'module' => 'processing',
                'description' => 'Delete external processing orders and reverse inventory/GL/supplier balances',
            ]
        );

        $superAdminSlug = config('rbac.super_admin_role_slug', 'super-admin');
        foreach ([$superAdminSlug, 'admin-preset'] as $roleSlug) {
            $role = Role::query()
                ->where('guard_name', $guard)
                ->where('slug', $roleSlug)
                ->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        // مستخدمو قسم Admin بدون أدوار يحصلون على Super Admin عادةً من RbacFoundationSeeder.
        // نضمن منح الصلاحية لأي دور مرتبط بمستخدم Admin.
        $adminUsers = User::query()->where('department', 'Admin')->get();
        foreach ($adminUsers as $admin) {
            foreach ($admin->roles as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        if (class_exists(\App\Services\Rbac\RbacStampService::class)) {
            app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
        }
    }
}
