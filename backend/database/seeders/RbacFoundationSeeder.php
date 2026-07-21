<?php

namespace Database\Seeders;

use App\Models\DepartmentRoleTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Orders\OrderStatusVisibilityService;
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
            ['module' => 'orders', 'slug' => 'orders.cancel_line', 'name' => 'Cancel order line items'],
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
            ['module' => 'finance', 'slug' => 'finance.tree_account.balance_adjustment', 'name' => 'Adjust tree account opening balance'],
            ['module' => 'finance', 'slug' => 'expenses.purge_all', 'name' => 'Purge All Expenses'],
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
            ['module' => 'system', 'slug' => 'system.activity_log', 'name' => 'View Activity Log'],
            ['module' => 'system', 'slug' => 'system.activity_log.edit', 'name' => 'Open Activity Log Entity For Edit'],
            ['module' => 'whatsapp', 'slug' => 'whatsapp.assign_numbers', 'name' => 'assign to whatsapp number'],
            ['module' => 'categories', 'slug' => 'categories.view', 'name' => 'View Categories'],
            ['module' => 'categories', 'slug' => 'categories.manage', 'name' => 'Manage Categories'],
            ['module' => 'suppliers', 'slug' => 'suppliers.view', 'name' => 'View Suppliers'],
            ['module' => 'suppliers', 'slug' => 'suppliers.delete', 'name' => 'Delete Suppliers'],
            ['module' => 'suppliers', 'slug' => 'suppliers.purge_all', 'name' => 'Purge All Suppliers'],
            ['module' => 'purchases', 'slug' => 'purchases.view', 'name' => 'View Purchases'],
            ['module' => 'manufacturing', 'slug' => 'manufacturing.view', 'name' => 'View Manufacturing'],
            ['module' => 'manufacturing', 'slug' => 'manufacturing.edit_recipe', 'name' => 'Edit Manufacturing Recipes'],
            ['module' => 'manufacturing', 'slug' => 'manufacturing.delete_recipe', 'name' => 'Delete Manufacturing Recipes'],
            ['module' => 'manufacturing', 'slug' => 'manufacturing.delete_order', 'name' => 'Delete Manufacturing Orders'],
            ['module' => 'processing', 'slug' => 'processing.view', 'name' => 'View External Processing'],
            ['module' => 'processing', 'slug' => 'processing.create', 'name' => 'Create External Processing Documents'],
            ['module' => 'processing', 'slug' => 'processing.post', 'name' => 'Post External Processing Documents'],
            ['module' => 'processing', 'slug' => 'processing.delete_order', 'name' => 'Delete External Processing Orders'],
            ['module' => 'processing', 'slug' => 'nav.processing', 'name' => 'Nav: External Processing section'],
            ['module' => 'notifications', 'slug' => 'notifications.review_filter', 'name' => 'Notifications review filter'],
            ['module' => 'nav', 'slug' => 'nav.receipts', 'name' => 'Nav: Receipts section'],
            ['module' => 'nav', 'slug' => 'nav.receipts.quotes', 'name' => 'Nav: Price quotes'],
            ['module' => 'offers', 'slug' => 'offers.view_all', 'name' => 'View all price offers'],
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

        foreach (app(OrderStatusVisibilityService::class)->permissionDefinitions() as $statusPerm) {
            $definitions[] = [
                'module' => 'orders_statuses',
                'slug' => $statusPerm['slug'],
                'name' => $statusPerm['name'],
            ];
        }

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

        // «طلب جديد» مقصورة على خدمة العملاء فقط؛ تُنزع حتى من Super Admin افتراضياً
        // (يمكن منحها يدوياً لمن يلزم). للوصول الكامل دون قيود استخدم rbac.super_admin_emails.
        $newStatusPerm = Permission::query()
            ->where('guard_name', $guard)
            ->where('slug', 'orders.view_status.new')
            ->first();
        if ($newStatusPerm) {
            $superAdmin->revokePermissionTo($newStatusPerm);
        }

        $adminPreset = Role::query()->firstOrCreate(
            ['slug' => 'admin-preset', 'guard_name' => $guard],
            ['name' => 'Administrator Preset', 'description' => 'Recommended ERP administrator bundle']
        );

        $presetSlugs = [
            'orders.view', 'orders.create', 'orders.edit', 'orders.cancel_line', 'orders.change_status', 'orders.export', 'orders.shopify.review',
            // «طلب جديد» (orders.view_status.new) مقصورة على خدمة العملاء — غير مشمولة هنا.
            'orders.view_status.confirmed', 'orders.view_status.partial_ship',
            'orders.view_status.shipped', 'orders.view_status.delivered', 'orders.view_status.received',
            'orders.view_status.collected', 'orders.view_status.postponed', 'orders.view_status.archived',
            'orders.view_status.maintained', 'orders.view_status.refused', 'orders.view_status.cancelled',
            'finance.view', 'finance.edit',
            'employees.view', 'employees.create', 'employees.attendance',
            'inventory.view', 'inventory.transfer',
            'settings.view', 'system.rbac', 'system.activity_log', 'system.activity_log.edit',
            'categories.view', 'categories.manage',
            'suppliers.view', 'purchases.view', 'manufacturing.view', 'manufacturing.edit_recipe',
            'processing.view', 'processing.delete_order',
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

        $customerServicePreset = Role::query()->firstOrCreate(
            ['slug' => 'customer-service-preset', 'guard_name' => $guard],
            [
                'name' => 'Customer Service Preset',
                'description' => 'خدمة العملاء — يشمل عرض الطلبات الجديدة',
            ]
        );

        $csSlugs = [
            'orders.view', 'orders.change_status', 'orders.edit', 'orders.cancel_line',
            'orders.view_status.new',
            'nav.receipts.quotes',
            'orders.convert_from_offer',
        ];
        $csIds = Permission::query()->where('guard_name', $guard)->whereIn('slug', $csSlugs)->pluck('id');
        $customerServicePreset->syncPermissions($csIds);

        DepartmentRoleTemplate::query()->updateOrCreate(
            ['department' => 'Customer Service'],
            ['role_id' => $customerServicePreset->id]
        );

        $this->seedDepartmentStatusVisibility($guard);

        $admins = User::query()->where('department', 'Admin')->get();
        foreach ($admins as $admin) {
            if ($admin->roles()->count() === 0) {
                $admin->assignRole($superAdmin);
            }
        }

        app(\App\Services\Rbac\RbacStampService::class)->bumpAfterRolePermissionsChanged();
    }

    /**
     * يمنح دور كل قسم حالاته الافتراضية (دمج لا حذف) حتى تتحكم مربعات الصلاحيات
     * في ظهور الطلبات فعلياً دون تعطيل الأقسام القائمة.
     */
    private function seedDepartmentStatusVisibility(string $guard): void
    {
        $statusKeyToSlug = [];
        foreach (config('order_status_visibility.statuses', []) as $key => $def) {
            $statusKeyToSlug[$key] = strtolower($def['permission']);
        }
        $allStatusSlugs = array_values($statusKeyToSlug);

        /** @var array<string, \App\Models\Permission> $statusPermsBySlug */
        $statusPermsBySlug = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('slug', $allStatusSlugs)
            ->get()
            ->keyBy('slug');

        foreach (config('order_status_visibility.department_default_status_keys', []) as $department => $keys) {
            $template = DepartmentRoleTemplate::query()
                ->where('department', $department)
                ->with('role')
                ->first();

            if (! $template || ! $template->role) {
                continue;
            }

            $desiredSlugs = $keys === '*'
                ? $allStatusSlugs
                : array_values(array_filter(array_map(
                    fn ($key) => $statusKeyToSlug[$key] ?? null,
                    (array) $keys
                )));

            // حاسم: امنح المطلوب وانزع كل حالة غير مطلوبة (givePermissionTo يدمج فقط).
            foreach ($allStatusSlugs as $slug) {
                $perm = $statusPermsBySlug->get($slug);
                if (! $perm) {
                    continue;
                }

                if (in_array($slug, $desiredSlugs, true)) {
                    $template->role->givePermissionTo($perm);
                } else {
                    $template->role->revokePermissionTo($perm);
                }
            }
        }
    }
}
