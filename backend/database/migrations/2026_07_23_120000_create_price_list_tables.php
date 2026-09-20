<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Price list for summer 26');
            $table->timestamps();
        });

        DB::table('price_list_settings')->insert([
            'title' => 'Price list for summer 26',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('price_list_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('product_name');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('photo1')->nullable();
            $table->string('photo2')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
        Schema::dropIfExists('price_list_settings');
    }
};
