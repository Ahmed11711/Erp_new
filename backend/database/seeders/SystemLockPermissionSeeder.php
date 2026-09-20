<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * صلاحيات قفل النظام، وإعادة تشغيله، واستثناء مستخدمين أثناء القفل.
 *
 * php artisan db:seed --class=SystemLockPermissionSeeder
 */
class SystemLockPermissionSeeder extends Seeder
{
    public const LOCK_SLUG = 'system.lock';

    public const UNLOCK_SLUG = 'system.unlock';

    public const BYPASS_SLUG = 'system.lock_bypass';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $lock = Permission::query()->firstOrCreate(
            ['slug' => self::LOCK_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Lock the system for other users',
                'module' => 'system',
                'description' => 'Show the lock button and lock the ERP for default users',
            ]
        );

        $unlock = Permission::query()->firstOrCreate(
            ['slug' => self::UNLOCK_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Unlock / restart the system',
                'module' => 'system',
                'description' => 'Restart the ERP after a system lock (typically admin only)',
            ]
        );

        $bypass = Permission::query()->firstOrCreate(
            ['slug' => self::BYPASS_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Bypass system lock',
                'module' => 'system',
                'description' => 'Keep using the ERP while it is locked for other users (no lock button)',
            ]
        );

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdminSlug = config('rbac.super_admin_role_slug', 'super-admin');
        $superAdmin = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', $superAdminSlug)
            ->first();

        if ($superAdmin) {
            foreach ([$lock, $unlock, $bypass] as $permission) {
                if (! $superAdmin->hasPermissionTo($permission)) {
                    $superAdmin->givePermissionTo($permission);
                }
            }
        }

        $adminPreset = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'admin-preset')
            ->first();

        if ($adminPreset) {
            foreach ([$lock, $unlock] as $permission) {
                if (! $adminPreset->hasPermissionTo($permission)) {
                    $adminPreset->givePermissionTo($permission);
                }
            }
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }
}
