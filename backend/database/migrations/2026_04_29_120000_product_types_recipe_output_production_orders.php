<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'product_type')) {
                    $table->string('product_type', 32)->nullable()->after('recipe_id');
                }
                if (! Schema::hasColumn('categories', 'allow_wip_sale')) {
                    $table->boolean('allow_wip_sale')->default(false)->after('product_type');
                }
            });

            $this->backfillProductTypesFromWarehouse();
        }

        if (Schema::hasTable('recipes')) {
            Schema::table('recipes', function (Blueprint $table) {
                if (! Schema::hasColumn('recipes', 'output_item_id')) {
                    $table->foreignId('output_item_id')
                        ->nullable()
                        ->after('description')
                        ->constrained('categories')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
                $table->foreignId('output_product_id')->constrained('categories')->restrictOnDelete();
                $table->decimal('quantity', 18, 6);
                $table->string('status', 32)->default('draft');
                $table->decimal('materials_cost_total', 20, 4)->nullable();
                $table->decimal('additional_costs_total', 20, 4)->nullable();
                $table->decimal('total_output_cost', 20, 4)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('notes', 512)->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index('output_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_orders')) {
            Schema::dropIfExists('production_orders');
        }

        if (Schema::hasTable('recipes') && Schema::hasColumn('recipes', 'output_item_id')) {
            Schema::table('recipes', function (Blueprint $table) {
                $table->dropForeign(['output_item_id']);
                $table->dropColumn('output_item_id');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'allow_wip_sale')) {
                    $table->dropColumn('allow_wip_sale');
                }
                if (Schema::hasColumn('categories', 'product_type')) {
                    $table->dropColumn('product_type');
                }
            });
        }
    }

    private function backfillProductTypesFromWarehouse(): void
    {
        DB::table('categories')
            ->whereNull('product_type')
            ->where('warehouse', 'مخزن منتج تام')
            ->update(['product_type' => 'finished']);

        DB::table('categories')
            ->whereNull('product_type')
            ->where('warehouse', 'مخزن منتج تحت التشغيل')
            ->update(['product_type' => 'semi_finished']);

        DB::table('categories')
            ->whereNull('product_type')
            ->where('warehouse', 'مخزن مواد خام')
            ->update(['product_type' => 'raw_material']);
    }
};
