<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_products', function (Blueprint $table) {
            $table->string('image_url_1', 1000)->nullable()->after('quantity');
            $table->string('image_url_2', 1000)->nullable()->after('image_url_1');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_products', function (Blueprint $table) {
            $table->dropColumn(['image_url_1', 'image_url_2']);
        });
    }
};
