<?php

namespace Database\Seeders;

use App\Models\DepartmentRoleTemplate;
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

    /**
     * أدوار «قسم» قديمة قد تُفعَّل عليها الصلاحية في واجهة الأدوار بينما
     * قالب القسم الفعلي يشير لدور آخر (مثل customer-service-preset).
     *
     * @var array<string, string> role_slug => department name
     */
    private const ORPHAN_DEPT_ROLE_TO_DEPARTMENT = [
        'dept-customer-service' => 'Customer Service',
        'dept-data-entry' => 'Data Entry',
        'dept-corporate' => 'Corparates',
        'dept-admin' => 'Admin',
        'dept-am-logistics' => 'Account Management',
        'dept-shipping-mgmt' => 'Shipping Management',
        'dept-financial' => 'Financial Accounts',
        'dept-operation-mgmt' => 'Operation Management',
        'dept-finance-ops' => 'Finance and operations management',
        'dept-operation-specialist' => 'Operation Specialist',
        'dept-review-mgmt' => 'Review Management',
    ];

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

        $adminPreset = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'admin-preset')
            ->first();

        if ($adminPreset && ! $adminPreset->hasPermissionTo($permission)) {
            $adminPreset->givePermissionTo($permission);
        }

        // إن وُجدت الصلاحية على دور قسم «يتيم» بينما قالب القسم يستخدم دوراً آخر — انسخها للقالب الفعلي
        foreach (self::ORPHAN_DEPT_ROLE_TO_DEPARTMENT as $roleSlug => $department) {
            $orphan = Role::query()
                ->where('guard_name', $guard)
                ->where('slug', $roleSlug)
                ->first();

            if (! $orphan || ! $orphan->hasPermissionTo($permission)) {
                continue;
            }

            $template = DepartmentRoleTemplate::query()
                ->where('department', $department)
                ->with('role')
                ->first();

            if (! $template?->role) {
                continue;
            }

            if ((int) $template->role_id === (int) $orphan->id) {
                continue;
            }

            if (! $template->role->hasPermissionTo($permission)) {
                $template->role->givePermissionTo($permission);
            }
        }

        // Logistics Specialist يشارك غالباً نفس دور AM
        $logisticsTpl = DepartmentRoleTemplate::query()
            ->where('department', 'Logistics Specialist')
            ->with('role')
            ->first();
        $amOrphan = Role::query()
            ->where('guard_name', $guard)
            ->where('slug', 'dept-am-logistics')
            ->first();
        if (
            $logisticsTpl?->role
            && $amOrphan
            && $amOrphan->hasPermissionTo($permission)
            && ! $logisticsTpl->role->hasPermissionTo($permission)
        ) {
            $logisticsTpl->role->givePermissionTo($permission);
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }
}
