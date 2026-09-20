<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items: product_id maps to categories.id (SKU master in this codebase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transaction_id')->constrained('stock_transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('categories');
            $table->decimal('qty', 18, 6);
            $table->decimal('price', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            /** Destination SKU for warehouse transfers (same physical item in another warehouse row). */
            $table->foreignId('to_product_id')->nullable()->constrained('categories');
            /**
             * For MANUAL_ADJUSTMENT only: explicit line direction when stock_direction on type is neutral.
             */
            $table->string('qty_direction', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transaction_items');
    }
};
