<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_imports', function (Blueprint $table) {
            $table->id();
            $table->string('import_token', 64)->unique();
            $table->string('filename', 255)->nullable();
            $table->enum('status', ['pending', 'previewed', 'confirmed', 'cancelled', 'failed'])->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('new_items_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('adjusted_rows')->default(0);
            $table->json('preview_data')->nullable();
            $table->json('warnings')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('import_token');
        });

        Schema::create('inventory_count_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('inventory_count_imports')->cascadeOnDelete();
            $table->unsignedInteger('excel_row_number');
            $table->string('excel_product_name', 512)->nullable();
            $table->string('excel_warehouse_name', 255)->nullable();
            $table->decimal('excel_quantity', 18, 6)->default(0);
            $table->decimal('excel_unit_cost', 16, 4)->nullable();

            $table->enum('match_type', ['exact_sku', 'exact_name', 'fuzzy', 'new'])->default('new');
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->string('matched_name', 512)->nullable();
            $table->foreignId('matched_category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->decimal('system_quantity', 18, 6)->nullable();
            $table->decimal('quantity_difference', 18, 6)->nullable();
            $table->string('adjustment_direction', 4)->nullable();

            $table->foreignId('assigned_stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->string('assigned_warehouse_name', 255)->nullable();
            $table->string('classification_method', 32)->nullable();

            $table->boolean('is_new_product')->default(false);
            $table->boolean('is_duplicate_row')->default(false);
            $table->unsignedBigInteger('merged_into_row_id')->nullable();

            $table->json('warnings')->nullable();
            $table->enum('row_status', ['pending', 'applied', 'skipped', 'error'])->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['import_id', 'row_status']);
            $table->index('matched_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_import_rows');
        Schema::dropIfExists('inventory_count_imports');
    }
};
