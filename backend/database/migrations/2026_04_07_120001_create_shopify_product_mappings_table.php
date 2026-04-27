<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->nullable()->index();
            $table->unsignedBigInteger('shopify_variant_id')->unique();
            $table->unsignedBigInteger('shopify_product_id')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_product_mappings');
    }
};
