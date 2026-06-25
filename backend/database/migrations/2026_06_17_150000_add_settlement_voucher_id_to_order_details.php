<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_details') && ! Schema::hasColumn('order_details', 'settlement_voucher_id')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->unsignedBigInteger('settlement_voucher_id')->nullable()->after('settlement_status');
                $table->index('settlement_voucher_id', 'order_details_settlement_voucher_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'settlement_voucher_id')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropIndex('order_details_settlement_voucher_id_index');
                $table->dropColumn('settlement_voucher_id');
            });
        }
    }
};
