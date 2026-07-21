<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل دفعات الشحن (شحن جزئي متكرر) + دعم حالة «تسليم جزئي».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_shipments')) {
            Schema::create('order_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedInteger('shipment_seq')->default(1);
                $table->date('shipped_at')->nullable();
                $table->unsignedBigInteger('shipping_company_id')->nullable()->index();
                $table->string('payment_way', 32)->nullable();
                $table->decimal('lines_total', 14, 2)->default(0);
                $table->decimal('cogs_total', 14, 4)->default(0);
                $table->boolean('is_final')->default(false);
                $table->string('status_after', 64)->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'shipment_seq']);
            });
        }

        if (! Schema::hasTable('order_shipment_lines')) {
            Schema::create('order_shipment_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_shipment_id')->index();
                $table->unsignedBigInteger('order_product_id')->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->decimal('quantity', 14, 3)->default(0);
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->decimal('unit_cost', 14, 4)->default(0);
                $table->decimal('line_cogs', 14, 4)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipment_lines');
        Schema::dropIfExists('order_shipments');
    }
};
