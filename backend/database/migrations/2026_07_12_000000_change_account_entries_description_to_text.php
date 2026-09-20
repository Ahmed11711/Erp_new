<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع عمود description في account_entries إلى TEXT لاستيعاب أوصاف التدقيق الطويلة
 * (تسوية الرصيد / الرصيد الافتتاحي). يُستخدم SQL خام لضمان التطبيق دون الاعتماد على doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('account_entries', 'description')) {
            DB::statement('ALTER TABLE account_entries MODIFY description TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('account_entries', 'description')) {
            DB::statement('ALTER TABLE account_entries MODIFY description VARCHAR(191) NULL');
        }
    }
};
