<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يسمح بسطور كشف شركة الشحن غير المرتبطة بطلب (سند قبض/صرف مباشر).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->after('order_id')->constrained('vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn('voucher_id');
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders');
        });
    }
};
