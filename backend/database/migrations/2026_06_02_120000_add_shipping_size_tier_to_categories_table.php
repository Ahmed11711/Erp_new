<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'shipping_size_tier')) {
                $table->string('shipping_size_tier', 16)->nullable()->after('product_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'shipping_size_tier')) {
                $table->dropColumn('shipping_size_tier');
            }
        });
    }
};
