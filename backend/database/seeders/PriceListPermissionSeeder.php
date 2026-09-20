<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * صلاحيات قائمة الأسعار (Price List).
 *
 * php artisan db:seed --class=PriceListPermissionSeeder
 */
class PriceListPermissionSeeder extends Seeder
{
    public const NAV_SLUG = 'nav.price_list';

    public const EDIT_SLUG = 'price_list.edit';

    public const DELETE_SLUG = 'price_list.delete';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $nav = Permission::query()->firstOrCreate(
            ['slug' => self::NAV_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Nav: Price list',
                'module' => 'nav',
                'description' => 'Access the Magalis price list catalog page',
            ]
        );

        $edit = Permission::query()->firstOrCreate(
            ['slug' => self::EDIT_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Edit price list items',
                'module' => 'price_list',
                'description' => 'Add and edit price list products and title',
            ]
        );

        $delete = Permission::query()->firstOrCreate(
            ['slug' => self::DELETE_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Delete price list items',
                'module' => 'price_list',
                'description' => 'Delete products from the price list',
            ]
        );

        $roleSlugs = [
            config('rbac.super_admin_role_slug', 'super-admin'),
            'admin-preset',
        ];

        foreach ($roleSlugs as $roleSlug) {
            $role = Role::query()
                ->where('guard_name', $guard)
                ->where('slug', $roleSlug)
                ->first();
            if (! $role) {
                continue;
            }
            foreach ([$nav, $edit, $delete] as $permission) {
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
