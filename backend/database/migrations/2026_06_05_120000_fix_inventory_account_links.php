<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * إصلاح ربط حسابات المخزون: ضبط detail_type على الحسابات الفرعية الطرفية،
 * وربط كل مخزن (stocks.asset_id) بحسابه الفرعي الصحيح بدل حساب "الأصول" الجذر.
 *
 * كان السبب أن stocks.asset_id كان يشير إلى الحساب الجذر (كود 1000)،
 * فكانت كل قيود المخزون تُرحَّل على الجذر وتبقى الفروع بصفر.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) ضبط detail_type على الحسابات الفرعية الطرفية حسب الكود (ثابت بغض النظر عن الاسم).
        $detailByCode = [
            '1000223' => 'inventory_raw',       // مخزون خامات / مواد خام
            '1000222' => 'inventory_wip',        // مخزون تحت التشغيل
            '1000221' => 'inventory_finished',   // مخزون منتج/إنتاج تام
        ];

        foreach ($detailByCode as $code => $detail) {
            $acc = DB::table('tree_accounts')->where('code', $code)->first();
            if ($acc) {
                DB::table('tree_accounts')->where('id', $acc->id)->update([
                    'detail_type' => $detail,
                    'updated_at' => now(),
                ]);
            }
        }

        // 2) التأكد من وجود عمود warehouse_type (احتياطاً لو لم تُشغّل الهجرة السابقة).
        if (Schema::hasTable('stocks') && ! Schema::hasColumn('stocks', 'warehouse_type')) {
            Schema::table('stocks', function ($table) {
                $table->string('warehouse_type', 32)->nullable();
            });
        }

        // 3) ربط كل مخزن بحسابه الفرعي الصحيح حسب اسم المخزن.
        $warehouseToCode = [
            'مخزن مواد خام' => ['code' => '1000223', 'type' => 'raw_materials'],
            'مخزن منتج تحت التشغيل' => ['code' => '1000222', 'type' => 'wip'],
            'مخزن منتج تام' => ['code' => '1000221', 'type' => 'finished_goods'],
        ];

        foreach ($warehouseToCode as $warehouseName => $cfg) {
            $acc = DB::table('tree_accounts')->where('code', $cfg['code'])->first();
            if (! $acc) {
                continue;
            }
            DB::table('stocks')
                ->where('name', $warehouseName)
                ->update([
                    'asset_id' => $acc->id,
                    'warehouse_type' => $cfg['type'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // لا نعكس detail_type/asset_id تلقائياً حتى لا نُعيد كسر الربط.
    }
};
