<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_dispatch_notes', function (Blueprint $table) {
            if (Schema::hasColumn('processing_dispatch_notes', 'internal_user_id')) {
                $table->dropForeign(['internal_user_id']);
                $table->dropColumn('internal_user_id');
            }

            if (! Schema::hasColumn('processing_dispatch_notes', 'shipping_company_id')) {
                $table->foreignId('shipping_company_id')
                    ->nullable()
                    ->after('representative_type')
                    ->constrained('shipping_companies')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('processing_dispatch_notes', function (Blueprint $table) {
            if (Schema::hasColumn('processing_dispatch_notes', 'shipping_company_id')) {
                $table->dropForeign(['shipping_company_id']);
                $table->dropColumn('shipping_company_id');
            }

            if (! Schema::hasColumn('processing_dispatch_notes', 'internal_user_id')) {
                $table->foreignId('internal_user_id')
                    ->nullable()
                    ->after('representative_type')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }
};
