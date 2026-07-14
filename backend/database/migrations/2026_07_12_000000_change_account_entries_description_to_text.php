<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع عمود description في account_entries من VARCHAR(255) إلى TEXT
 * لاستيعاب أوصاف التدقيق الطويلة (تسوية الرصيد / الرصيد الافتتاحي).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('account_entries', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }
};
