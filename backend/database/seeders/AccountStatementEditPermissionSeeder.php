<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * يضيف صلاحية فتح/تعديل المعاملات من كشف الحساب التفصيلي دون إعادة مزامنة أدوار المستخدمين.
 *
 * php artisan db:seed --class=AccountStatementEditPermissionSeeder
 */
class AccountStatementEditPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'finance.account_statement.edit';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Edit transactions from account statement',
                'module' => 'finance',
                'description' => 'Open and edit ledger lines from the detailed account statement report',
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
