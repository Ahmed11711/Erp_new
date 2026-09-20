<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * صلاحيات حذف عرض السعر وتعديل/حذف أصنافه.
 *
 * php artisan db:seed --class=OffersEditDeletePermissionSeeder
 */
class OffersEditDeletePermissionSeeder extends Seeder
{
    public const EDIT_SLUG = 'offers.edit';

    public const DELETE_SLUG = 'offers.delete';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $edit = Permission::query()->firstOrCreate(
            ['slug' => self::EDIT_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Edit price offer items',
                'module' => 'offers',
                'description' => 'Edit a price offer and add/remove/change its line items',
            ]
        );

        $delete = Permission::query()->firstOrCreate(
            ['slug' => self::DELETE_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Delete price offers',
                'module' => 'offers',
                'description' => 'Delete an entire price offer (when not converted or debt-posted)',
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
            foreach ([$edit, $delete] as $permission) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        // خدمة العملاء: تعديل الأصناف فقط (بدون حذف العرض بالكامل)
        $cs = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'customer-service-preset')
            ->first();
        if ($cs && ! $cs->hasPermissionTo($edit)) {
            $cs->givePermissionTo($edit);
        }

        if (class_exists(\App\Services\Rbac\RbacStampService::class)) {
            app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
        }
    }
}
