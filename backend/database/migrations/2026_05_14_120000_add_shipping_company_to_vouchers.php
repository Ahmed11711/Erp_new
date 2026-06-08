<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers') && ! Schema::hasColumn('vouchers', 'shipping_company_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->foreignId('shipping_company_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('shipping_companies')
                    ->nullOnDelete();
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('vouchers')) {
            DB::statement("ALTER TABLE vouchers MODIFY COLUMN voucher_type ENUM('client', 'supplier', 'shipping_company') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'shipping_company_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropForeign(['shipping_company_id']);
                $table->dropColumn('shipping_company_id');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('vouchers')) {
            DB::statement("ALTER TABLE vouchers MODIFY COLUMN voucher_type ENUM('client', 'supplier') NOT NULL");
        }
    }
};
