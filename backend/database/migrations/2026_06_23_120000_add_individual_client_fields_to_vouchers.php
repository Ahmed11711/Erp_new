<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vouchers')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'client_kind')) {
                $table->string('client_kind', 20)->nullable()->after('client_id');
            }
            if (! Schema::hasColumn('vouchers', 'individual_customer_phone')) {
                $table->string('individual_customer_phone', 32)->nullable()->after('client_kind');
            }
            if (! Schema::hasColumn('vouchers', 'individual_customer_name')) {
                $table->string('individual_customer_name', 255)->nullable()->after('individual_customer_phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vouchers')) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) {
            if (Schema::hasColumn('vouchers', 'individual_customer_name')) {
                $table->dropColumn('individual_customer_name');
            }
            if (Schema::hasColumn('vouchers', 'individual_customer_phone')) {
                $table->dropColumn('individual_customer_phone');
            }
            if (Schema::hasColumn('vouchers', 'client_kind')) {
                $table->dropColumn('client_kind');
            }
        });
    }
};
