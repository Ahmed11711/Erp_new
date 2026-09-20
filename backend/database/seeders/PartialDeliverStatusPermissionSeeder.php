<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * صلاحية عرض حالة «تسليم جزئي» — تُمنح لكل دور يملك شحن جزئي.
 */
class PartialDeliverStatusPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $rbacGuard = config('rbac.guard', $guard);

        $perm = Permission::query()->firstOrCreate(
            ['slug' => 'orders.view_status.partial_deliver', 'guard_name' => $rbacGuard],
            [
                'name' => 'View Partially Delivered Orders',
                'module' => 'orders_statuses',
                'description' => null,
            ]
        );

        $partialShip = Permission::query()
            ->where('guard_name', $rbacGuard)
            ->where('slug', 'orders.view_status.partial_ship')
            ->first();

        if (! $partialShip) {
            $this->command?->warn('orders.view_status.partial_ship not found — skipped role sync.');

            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $partialShip->id)
            ->pluck('role_id');

        $attached = 0;
        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $perm->id)
                ->where('role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('role_has_permissions')->insert([
                'permission_id' => $perm->id,
                'role_id' => $roleId,
            ]);
            $attached++;
        }

        // امسح كاش Spatie إن وُجد
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command?->info("partial_deliver attached to {$attached} role(s).");
    }
}
