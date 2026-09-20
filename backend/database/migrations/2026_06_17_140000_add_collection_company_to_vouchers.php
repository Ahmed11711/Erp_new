<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers') && ! Schema::hasColumn('vouchers', 'collection_company_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->foreignId('collection_company_id')
                    ->nullable()
                    ->after('shipping_company_id')
                    ->constrained('collection_companies')
                    ->nullOnDelete();
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('vouchers')) {
            DB::statement("ALTER TABLE vouchers MODIFY COLUMN voucher_type ENUM('client', 'supplier', 'shipping_company', 'collection_company') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vouchers') && Schema::hasColumn('vouchers', 'collection_company_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropForeign(['collection_company_id']);
                $table->dropColumn('collection_company_id');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('vouchers')) {
            DB::statement("ALTER TABLE vouchers MODIFY COLUMN voucher_type ENUM('client', 'supplier', 'shipping_company') NOT NULL");
        }
    }
};
