<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shopify_needs_product_review')) {
                $table->boolean('shopify_needs_product_review')
                    ->default(false)
                    ->after('shopify_review_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shopify_needs_product_review')) {
                $table->dropColumn('shopify_needs_product_review');
            }
        });
    }
};
