<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('colors')) {
            Schema::create('colors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191);
                $table->string('slug', 191)->unique();
                $table->string('hex', 16)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'parent_item_id')) {
                    $table->foreignId('parent_item_id')
                        ->nullable()
                        ->constrained('categories')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('categories', 'supports_color')) {
                    $table->boolean('supports_color')->default(false);
                }
                if (! Schema::hasColumn('categories', 'color_id')) {
                    $table->foreignId('color_id')
                        ->nullable()
                        ->constrained('colors')
                        ->nullOnDelete();
                }
            });

            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'parent_item_id') && Schema::hasColumn('categories', 'color_id')) {
                    $table->index(['parent_item_id', 'color_id'], 'categories_parent_color_idx');
                }
            });
        }

        if (! Schema::hasTable('category_color') && Schema::hasTable('categories') && Schema::hasTable('colors')) {
            Schema::create('category_color', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->foreignId('color_id')->constrained('colors')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['category_id', 'color_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('category_color')) {
            Schema::dropIfExists('category_color');
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                try {
                    $table->dropIndex('categories_parent_color_idx');
                } catch (\Throwable) {
                    // index may not exist
                }
                if (Schema::hasColumn('categories', 'color_id')) {
                    $table->dropForeign(['color_id']);
                    $table->dropColumn('color_id');
                }
                if (Schema::hasColumn('categories', 'supports_color')) {
                    $table->dropColumn('supports_color');
                }
                if (Schema::hasColumn('categories', 'parent_item_id')) {
                    $table->dropForeign(['parent_item_id']);
                    $table->dropColumn('parent_item_id');
                }
            });
        }

        if (Schema::hasTable('colors')) {
            Schema::dropIfExists('colors');
        }
    }
};
