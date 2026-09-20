<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_month_paids', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
        });

        DB::statement('ALTER TABLE `employee_month_paids` MODIFY `bank_id` BIGINT UNSIGNED NULL');

        Schema::table('employee_month_paids', function (Blueprint $table) {
            $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            $table->foreignId('safe_id')->nullable()->constrained('safes')->nullOnDelete();
            $table->foreignId('service_account_id')->nullable()->constrained('service_accounts')->nullOnDelete();
            $table->string('payment_source', 32)->nullable()->comment('bank|safe|service_account');
        });
    }

    public function down(): void
    {
        Schema::table('employee_month_paids', function (Blueprint $table) {
            $table->dropForeign(['safe_id']);
            $table->dropForeign(['service_account_id']);
            $table->dropColumn(['safe_id', 'service_account_id', 'payment_source']);
            $table->dropForeign(['bank_id']);
        });

        DB::statement('ALTER TABLE `employee_month_paids` MODIFY `bank_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('employee_month_paids', function (Blueprint $table) {
            $table->foreign('bank_id')->references('id')->on('banks');
        });
    }
};
