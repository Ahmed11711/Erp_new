<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * يضيف صلاحية عرض كل عروض الأسعار (بدلاً من الاعتماد على قسم Admin).
 * بدون هذه الصلاحية يرى كل مستخدم عروضه فقط.
 *
 * php artisan db:seed --class=OffersViewAllPermissionSeeder
 */
class OffersViewAllPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'offers.view_all';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'View all price offers',
                'module' => 'offers',
                'description' => 'See every price offer regardless of who created it; without this permission users only see their own offers',
            ]
        );

        $superAdminSlug = config('rbac.super_admin_role_slug', 'super-admin');
        $superAdmin = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', $superAdminSlug)
            ->first();

        if ($superAdmin && ! $superAdmin->hasPermissionTo($permission)) {
            $superAdmin->givePermissionTo($permission);
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }
}
