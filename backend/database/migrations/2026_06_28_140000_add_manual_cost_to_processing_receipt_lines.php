<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_receipt_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('processing_receipt_lines', 'dest_unit_cost')) {
                $table->decimal('dest_unit_cost', 20, 4)->nullable()->after('allocated_service_cost');
            }
            if (! Schema::hasColumn('processing_receipt_lines', 'dest_sell_price')) {
                $table->decimal('dest_sell_price', 20, 4)->nullable()->after('dest_unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('processing_receipt_lines', function (Blueprint $table) {
            foreach (['dest_unit_cost', 'dest_sell_price'] as $col) {
                if (Schema::hasColumn('processing_receipt_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
