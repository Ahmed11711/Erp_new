<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $definitions = [
            ['module' => 'orders', 'slug' => 'orders.view', 'name' => 'View Orders'],
            ['module' => 'orders', 'slug' => 'orders.create', 'name' => 'Create Orders'],
            ['module' => 'orders', 'slug' => 'orders.edit', 'name' => 'Edit Orders'],
            ['module' => 'orders', 'slug' => 'orders.delete', 'name' => 'Delete Orders'],
            ['module' => 'orders', 'slug' => 'orders.change_status', 'name' => 'Change Order Status'],
            ['module' => 'orders', 'slug' => 'orders.export', 'name' => 'Export Orders'],
            ['module' => 'orders', 'slug' => 'orders.assign_driver', 'name' => 'Assign Driver'],
            ['module' => 'orders', 'slug' => 'orders.shopify.review', 'name' => 'Review imported Shopify orders'],
            ['module' => 'finance', 'slug' => 'finance.view', 'name' => 'View Finance'],
            ['module' => 'finance', 'slug' => 'finance.create', 'name' => 'Create Finance'],
            ['module' => 'finance', 'slug' => 'finance.edit', 'name' => 'Edit Finance'],
            ['module' => 'finance', 'slug' => 'finance.delete', 'name' => 'Delete Finance'],
            ['module' => 'finance', 'slug' => 'finance.approve', 'name' => 'Approve Finance'],
            ['module' => 'employees', 'slug' => 'employees.view', 'name' => 'View Employees'],
            ['module' => 'employees', 'slug' => 'employees.create', 'name' => 'Create Employees'],
            ['module' => 'employees', 'slug' => 'employees.edit', 'name' => 'Edit Employees'],
            ['module' => 'employees', 'slug' => 'employees.delete', 'name' => 'Delete Employees'],
            ['module' => 'employees', 'slug' => 'employees.attendance', 'name' => 'Employees Attendance'],
            ['module' => 'employees', 'slug' => 'employees.salary', 'name' => 'Employees Salary'],
            ['module' => 'inventory', 'slug' => 'inventory.view', 'name' => 'View Inventory'],
            ['module' => 'inventory', 'slug' => 'inventory.create', 'name' => 'Create Inventory'],
            ['module' => 'inventory', 'slug' => 'inventory.edit', 'name' => 'Edit Inventory'],
            ['module' => 'inventory', 'slug' => 'inventory.delete', 'name' => 'Delete Inventory'],
            ['module' => 'inventory', 'slug' => 'inventory.transfer', 'name' => 'Inventory Transfer'],
            ['module' => 'settings', 'slug' => 'settings.view', 'name' => 'View Settings'],
            ['module' => 'settings', 'slug' => 'settings.edit', 'name' => 'Edit Settings'],
            ['module' => 'system', 'slug' => 'system.rbac', 'name' => 'Manage RBAC'],
            ['module' => 'whatsapp', 'slug' => 'whatsapp.assign_numbers', 'name' => 'assign to whatsapp number'],
            ['module' => 'categories', 'slug' => 'categories.view', 'name' => 'View Categories'],
            ['module' => 'categories', 'slug' => 'categories.manage', 'name' => 'Manage Categories'],
            ['module' => 'suppliers', 'slug' => 'suppliers.view', 'name' => 'View Suppliers'],
            ['module' => 'purchases', 'slug' => 'purchases.view', 'name' => 'View Purchases'],
            ['module' => 'manufacturing', 'slug' => 'manufacturing.view', 'name' => 'View Manufacturing'],
            ['module' => 'notifications', 'slug' => 'notifications.review_filter', 'name' => 'Notifications review filter'],
            ['module' => 'nav', 'slug' => 'nav.receipts', 'name' => 'Nav: Receipts section'],
            ['module' => 'nav', 'slug' => 'nav.receipts.quotes', 'name' => 'Nav: Price quotes'],
            ['module' => 'nav', 'slug' => 'nav.receipts.admin', 'name' => 'Nav: Receipt admin forms'],
            ['module' => 'nav', 'slug' => 'nav.corporate', 'name' => 'Nav: Corporate sales'],
            ['module' => 'nav', 'slug' => 'nav.shopify', 'name' => 'Nav: Shopify integration (full legacy)'],
            ['module' => 'nav', 'slug' => 'nav.shopify.dashboard', 'name' => 'Nav: Shopify shipping dashboard'],
            ['module' => 'nav', 'slug' => 'nav.shopify.settings', 'name' => 'Nav: Shopify sync & connection settings'],
            ['module' => 'nav', 'slug' => 'nav.shipping.master', 'name' => 'Nav: Shipping master data'],
            ['module' => 'nav', 'slug' => 'nav.shipping.accounts_report', 'name' => 'Nav: Shipping receivables & collection reports'],
            ['module' => 'shipping', 'slug' => 'shipping.companies.manage', 'name' => 'Edit and delete shipping companies'],
            ['module' => 'shipping', 'slug' => 'shipping.companies.statement', 'name' => 'Shipping company account statement'],
            ['module' => 'shipping', 'slug' => 'collection_companies.manage', 'name' => 'Manage collection companies'],
            ['module' => 'customers', 'slug' => 'customer_companies.view', 'name' => 'View customer companies list'],
            ['module' => 'customers', 'slug' => 'customer_companies.manage', 'name' => 'Add, edit and link customer companies'],
            ['module' => 'customers', 'slug' => 'customer_companies.statement', 'name' => 'Customer company account statement'],
            ['module' => 'customers', 'slug' => 'customer_companies.collect', 'name' => 'Collect from customer company'],
            ['module' => 'shipping', 'slug' => 'settlements.manage', 'name' => 'Manage COD settlements'],
            ['module' => 'orders', 'slug' => 'orders.fulfillment.view', 'name' => 'View order fulfillment panel'],
            ['module' => 'orders', 'slug' => 'orders.liability.transfer', 'name' => 'Transfer order liability to collector'],
            ['module' => 'nav', 'slug' => 'nav.whatsapp_chat', 'name' => 'Nav: WhatsApp chat without assignment'],
        ];

        foreach ($definitions as $def) {
            Permission::query()->firstOrCreate(
                ['slug' => $def['slug'], 'guard_name' => $guard],
                [
                    'name' => $def['name'],
                    'module' => $def['module'],
                    'description' => null,
                ]
            );
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['slug' => config('rbac.super_admin_role_slug', 'super-admin'), 'guard_name' => $guard],
            [
                'name' => 'Super Admin',
                'description' => 'Full access — bypasses granular checks via resolver.',
            ]
        );

        $superAdmin->syncPermissions(Permission::query()->where('guard_name', $guard)->pluck('id'));

        $adminPreset = Role::query()->firstOrCreate(
            ['slug' => 'admin-preset', 'guard_name' => $guard],
            ['name' => 'Administrator Preset', 'description' => 'Recommended ERP administrator bundle']
        );

        $presetSlugs = [
            'orders.view', 'orders.create', 'orders.edit', 'orders.change_status', 'orders.export', 'orders.shopify.review',
            'finance.view', 'finance.edit',
            'employees.view', 'employees.create', 'employees.attendance',
            'inventory.view', 'inventory.transfer',
            'settings.view', 'system.rbac',
            'categories.view', 'categories.manage',
            'suppliers.view', 'purchases.view', 'manufacturing.view',
            'nav.receipts', 'nav.receipts.quotes', 'nav.receipts.admin',
            'nav.corporate', 'nav.shopify', 'nav.shopify.dashboard', 'nav.shopify.settings',
            'nav.shipping.master', 'nav.shipping.accounts_report',
            'shipping.companies.manage', 'shipping.companies.statement',
            'customer_companies.view', 'customer_companies.manage',
            'customer_companies.statement', 'customer_companies.collect',
            'nav.whatsapp_chat',
            'notifications.review_filter',
            'whatsapp.assign_numbers',
        ];

        $presetIds = Permission::query()->where('guard_name', $guard)->whereIn('slug', $presetSlugs)->pluck('id');
        $adminPreset->syncPermissions($presetIds);

        $admins = User::query()->where('department', 'Admin')->get();
        foreach ($admins as $admin) {
            if ($admin->roles()->count() === 0) {
                $admin->assignRole($superAdmin);
            }
        }
    }
}
