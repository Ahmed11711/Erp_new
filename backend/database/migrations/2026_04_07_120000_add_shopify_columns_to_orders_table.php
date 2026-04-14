<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shopify_order_id')) {
                $table->unsignedBigInteger('shopify_order_id')->nullable();
                $table->string('shopify_shop_domain')->nullable();
                $table->unique('shopify_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shopify_order_id')) {
                $table->dropUnique(['shopify_order_id']);
                $table->dropColumn(['shopify_order_id', 'shopify_shop_domain']);
            }
        });
    }
};
