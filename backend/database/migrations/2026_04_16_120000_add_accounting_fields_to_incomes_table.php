<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->unsignedBigInteger('revenue_tree_account_id')->nullable()->after('income_amount');
            $table->string('payment_type', 32)->nullable()->after('revenue_tree_account_id');
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_type');
            $table->unsignedBigInteger('safe_id')->nullable()->after('bank_id');
            $table->unsignedBigInteger('service_account_id')->nullable()->after('safe_id');
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropColumn([
                'revenue_tree_account_id',
                'payment_type',
                'bank_id',
                'safe_id',
                'service_account_id',
            ]);
        });
    }
};
