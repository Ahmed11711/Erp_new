<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'category_code')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('category_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_code')->nullable()->after('category_name');
        });
    }
};
