<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة نوع حساب «تسوية» (settlement) إلى عمود type.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tree_accounts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE tree_accounts MODIFY COLUMN type ENUM('asset', 'liability', 'equity', 'revenue', 'expense', 'settlement') NOT NULL");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tree_accounts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE tree_accounts SET type = 'equity' WHERE type = 'settlement'");
            DB::statement("ALTER TABLE tree_accounts MODIFY COLUMN type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL");
        }
    }
};
