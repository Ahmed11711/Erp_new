<?php

use App\Services\Processing\ProcessingWarehouseResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توحيد مسمى مخزن المواد المصروفة للمعالجين الخارجيين تحت اسم «مخزن التجهيز».
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = ProcessingWarehouseResolver::LEGACY_WAREHOUSE_NAMES;
        $name = ProcessingWarehouseResolver::WAREHOUSE_NAME;

        if (Schema::hasTable('stocks')) {
            DB::table('stocks')
                ->where(function ($q) use ($legacy) {
                    $q->where('warehouse_type', 'materials_at_vendor')
                        ->orWhereIn('name', $legacy);
                })
                ->update([
                    'name' => $name,
                    'name_en' => ProcessingWarehouseResolver::WAREHOUSE_NAME_EN,
                    'warehouse_type' => 'materials_at_vendor',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('tree_accounts')) {
            DB::table('tree_accounts')
                ->where(function ($q) use ($legacy) {
                    $q->where('code', '1000224')
                        ->orWhere('detail_type', 'inventory_subcontract')
                        ->orWhereIn('name', $legacy);
                })
                ->update([
                    'name' => $name,
                    'name_en' => 'Inventory — Processing warehouse',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('categories')) {
            DB::table('categories')->whereIn('warehouse', $legacy)->update(['warehouse' => $name]);
        }

        if (Schema::hasTable('inventory_movements')) {
            DB::table('inventory_movements')->whereIn('warehouse_name', $legacy)->update(['warehouse_name' => $name]);
        }
    }

    public function down(): void
    {
        // لا نُعيد الأسماء القديمة.
    }
};
