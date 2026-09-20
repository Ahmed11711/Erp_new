<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers_categories', function (Blueprint $table) {
            $table->foreignId('matched_category_id')
                ->nullable()
                ->after('category_name')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offers_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_category_id');
        });
    }
};
