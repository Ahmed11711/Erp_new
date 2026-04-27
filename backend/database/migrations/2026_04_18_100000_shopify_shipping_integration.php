<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 128)->index();
            $table->string('shop_domain', 255)->nullable()->index();
            $table->string('webhook_id', 64)->nullable()->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->longText('payload');
            $table->timestamps();
        });

        Schema::create('shopify_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id', 64)->unique();
            $table->string('shop_domain', 255)->nullable()->index();
            $table->string('topic', 128);
            $table->timestamps();
        });

        Schema::create('shopify_products', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain', 255)->nullable()->index();
            $table->unsignedBigInteger('shopify_product_id')->index();
            $table->unsignedBigInteger('shopify_variant_id')->nullable()->index();
            $table->unsignedBigInteger('inventory_item_id')->nullable()->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('sku', 191)->nullable()->index();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamp('last_pushed_to_shopify_at')->nullable();
            $table->timestamps();
            $table->unique(['shopify_product_id', 'shopify_variant_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shopify_financial_status')) {
                $table->string('shopify_financial_status', 64)->nullable();
            }
            if (! Schema::hasColumn('orders', 'shopify_fulfillment_status')) {
                $table->string('shopify_fulfillment_status', 64)->nullable();
            }
            if (! Schema::hasColumn('orders', 'shipping_tracking_number')) {
                $table->string('shipping_tracking_number', 191)->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'shipping_partner_status')) {
                $table->string('shipping_partner_status', 64)->nullable();
            }
        });

        Schema::table('order_products', function (Blueprint $table) {
            if (! Schema::hasColumn('order_products', 'shopify_line_item_id')) {
                $table->unsignedBigInteger('shopify_line_item_id')->nullable()->index();
            }
            if (! Schema::hasColumn('order_products', 'shopify_variant_id')) {
                $table->unsignedBigInteger('shopify_variant_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (Schema::hasColumn('order_products', 'shopify_line_item_id')) {
                $table->dropColumn('shopify_line_item_id');
            }
            if (Schema::hasColumn('order_products', 'shopify_variant_id')) {
                $table->dropColumn('shopify_variant_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['shopify_financial_status', 'shopify_fulfillment_status', 'shipping_tracking_number', 'shipping_partner_status'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('shopify_products');
        Schema::dropIfExists('shopify_webhook_receipts');
        Schema::dropIfExists('shopify_webhook_logs');
    }
};
