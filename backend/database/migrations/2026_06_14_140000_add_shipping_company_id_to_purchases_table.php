<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'shipping_company_id')) {
                $table->foreignId('shipping_company_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('shipping_companies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'shipping_company_id')) {
                $table->dropConstrainedForeignId('shipping_company_id');
            }
        });
    }
};
