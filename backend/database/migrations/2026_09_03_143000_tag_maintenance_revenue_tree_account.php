<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ربط حساب «إيرادات الصيانة» الموجود في الشجرة بـ detail_type=maintenance_revenue
 * حتى تُرحَّل طلبات الصيانة عليه بدل إيراد مبيعات البضاعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        $query = DB::table('tree_accounts')
            ->where('type', 'revenue')
            ->where(function ($q) {
                $q->where('name', 'like', '%إيراد%صيان%')
                    ->orWhere('name', 'like', '%ايراد%صيان%')
                    ->orWhere('name_en', 'like', '%maintenance%revenue%');
            })
            ->where(function ($q) {
                $q->whereNull('detail_type')->orWhere('detail_type', '');
            });

        if (DB::getSchemaBuilder()->hasColumn('tree_accounts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $query->update(['detail_type' => 'maintenance_revenue']);
    }

    public function down(): void
    {
        DB::table('tree_accounts')
            ->where('detail_type', 'maintenance_revenue')
            ->where(function ($q) {
                $q->where('name', 'like', '%إيراد%صيان%')
                    ->orWhere('name', 'like', '%ايراد%صيان%')
                    ->orWhere('name_en', 'like', '%maintenance%revenue%');
            })
            ->update(['detail_type' => null]);
    }
};
