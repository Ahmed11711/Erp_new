<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * صلاحية توليد أكواد الأصناف دفعة واحدة — تُمنح حالياً لسوبر أدمن وقالب الأدمن.
 *
 * php artisan db:seed --class=AssignItemCodesPermissionSeeder
 */
class AssignItemCodesPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'categories.assign_item_codes';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Assign sequential item codes in bulk',
                'module' => 'categories',
                'description' => 'Fill empty item codes in bulk (raw materials 10…, finished 30…)',
            ]
        );

        $roleSlugs = [
            config('rbac.super_admin_role_slug', 'super-admin'),
            'admin-preset',
        ];

        foreach ($roleSlugs as $slug) {
            $role = Role::query()
                ->where('guard_name', $guard)
                ->where('slug', $slug)
                ->first();
            if ($role && ! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }
}
