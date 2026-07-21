<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الكمية لدى المندوب تُتابع على مستندات التشغيل دون إنشاء صنف ظل في المخزن.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->nullableFk('processing_dispatch_lines', 'at_vendor_category_id');
        $this->nullableFk('processing_receipt_lines', 'at_vendor_category_id');

        if (Schema::hasTable('processing_material_balances')
            && Schema::hasColumn('processing_material_balances', 'at_vendor_category_id')) {
            try {
                Schema::table('processing_material_balances', function (Blueprint $table) {
                    $table->dropUnique('proc_mat_bal_order_cat_uq');
                });
            } catch (\Throwable) {
            }

            $this->nullableFk('processing_material_balances', 'at_vendor_category_id');

            try {
                Schema::table('processing_material_balances', function (Blueprint $table) {
                    $table->unique(['processing_order_id', 'category_id'], 'proc_mat_bal_order_source_uq');
                });
            } catch (\Throwable) {
            }
        }
    }

    private function nullableFk(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
        } else {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->unsignedBigInteger($column)->nullable()->change();
            });
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('categories')->nullOnDelete();
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // لا نُعيد إلزامية العمود.
    }
};
