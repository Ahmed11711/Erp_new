<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * يضيف صلاحية إدخال/تعديل الرصيد الافتتاحي وتسوية رصيد حساب في شجرة الحسابات عبر قيد يومي متوازن.
 *
 * php artisan db:seed --class=TreeAccountBalanceAdjustmentPermissionSeeder
 */
class TreeAccountBalanceAdjustmentPermissionSeeder extends Seeder
{
    public const PERMISSION_SLUG = 'finance.tree_account.balance_adjustment';

    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $permission = Permission::query()->firstOrCreate(
            ['slug' => self::PERMISSION_SLUG, 'guard_name' => $guard],
            [
                'name' => 'Adjust tree account opening balance',
                'module' => 'finance',
                'description' => 'Set or edit an opening balance / adjust a tree account balance via a balanced dated journal entry',
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
