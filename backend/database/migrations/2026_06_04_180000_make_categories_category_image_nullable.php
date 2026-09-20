<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'category_image')) {
            return;
        }

        DB::statement("ALTER TABLE categories MODIFY category_image VARCHAR(255) NULL DEFAULT ''");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'category_image')) {
            return;
        }

        DB::statement('ALTER TABLE categories MODIFY category_image VARCHAR(255) NOT NULL');
    }
};
