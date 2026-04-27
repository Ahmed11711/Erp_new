<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->string('recipe_name', 255);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('categories') && Schema::hasTable('recipes')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'item_code')) {
                    $table->string('item_code', 64)->nullable()->unique()->after('category_name');
                }
                if (!Schema::hasColumn('categories', 'color')) {
                    $table->string('color', 128)->nullable()->after('item_code');
                }
                if (!Schema::hasColumn('categories', 'recipe_id')) {
                    $table->foreignId('recipe_id')->nullable()->after('color')->constrained('recipes')->nullOnDelete();
                }
            });
        }

        if (!Schema::hasTable('recipe_ingredients') && Schema::hasTable('recipes') && Schema::hasTable('categories')) {
            Schema::create('recipe_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('categories')->restrictOnDelete();
                $table->decimal('quantity', 18, 6);
                $table->decimal('unit_cost', 16, 4)->nullable();
                $table->timestamps();
                $table->unique(['recipe_id', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recipe_ingredients')) {
            Schema::dropIfExists('recipe_ingredients');
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'recipe_id')) {
                    $table->dropForeign(['recipe_id']);
                    $table->dropColumn('recipe_id');
                }
                if (Schema::hasColumn('categories', 'color')) {
                    $table->dropColumn('color');
                }
                if (Schema::hasColumn('categories', 'item_code')) {
                    try {
                        $table->dropUnique(['item_code']);
                    } catch (\Throwable $e) {
                    }
                    $table->dropColumn('item_code');
                }
            });
        }

        if (Schema::hasTable('recipes')) {
            Schema::dropIfExists('recipes');
        }
    }
};
