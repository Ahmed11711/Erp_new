<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * صلاحية تحويل عرض السعر إلى طلب من الصفحة المخصصة.
 *
 * php artisan db:seed --class=OrdersConvertFromOfferPermissionSeeder
 */
class OrdersConvertFromOfferPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'orders.convert_from_offer';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Convert price offer to order',
                'module' => 'orders',
                'description' => 'Convert a price quotation into a new company sales order (dedicated screen)',
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

        // خدمة العملاء ترث من قالب القسم — بدون هذا لن يظهر «تحويل إلى طلب» لهم.
        $customerServicePreset = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'customer-service-preset')
            ->first();

        if ($customerServicePreset && ! $customerServicePreset->hasPermissionTo($permission)) {
            $customerServicePreset->givePermissionTo($permission);
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }
}
