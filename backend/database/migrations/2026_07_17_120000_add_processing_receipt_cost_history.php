<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_receipt_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('processing_receipt_lines', 'cost_apply_mode')) {
                $table->string('cost_apply_mode', 32)->nullable()->after('dest_sell_price');
            }
            if (! Schema::hasColumn('processing_receipt_lines', 'suggested_unit_cost')) {
                $table->decimal('suggested_unit_cost', 20, 4)->nullable()->after('cost_apply_mode');
            }
            if (! Schema::hasColumn('processing_receipt_lines', 'prior_unit_cost')) {
                $table->decimal('prior_unit_cost', 20, 4)->nullable()->after('suggested_unit_cost');
            }
            if (! Schema::hasColumn('processing_receipt_lines', 'prior_qty')) {
                $table->decimal('prior_qty', 18, 6)->nullable()->after('prior_unit_cost');
            }
            if (! Schema::hasColumn('processing_receipt_lines', 'resulting_unit_cost')) {
                $table->decimal('resulting_unit_cost', 20, 4)->nullable()->after('prior_qty');
            }
        });

        if (! Schema::hasTable('category_cost_histories')) {
            Schema::create('category_cost_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
                $table->string('source_type', 64);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('source_line_id')->nullable();
                $table->string('apply_mode', 32);
                $table->decimal('old_qty', 18, 6)->default(0);
                $table->decimal('old_unit_cost', 20, 4)->default(0);
                $table->decimal('old_total_cost', 20, 4)->default(0);
                $table->decimal('receipt_qty', 18, 6)->default(0);
                $table->decimal('receipt_unit_cost', 20, 4)->default(0);
                $table->decimal('receipt_total_cost', 20, 4)->default(0);
                $table->decimal('new_qty', 18, 6)->default(0);
                $table->decimal('new_unit_cost', 20, 4)->default(0);
                $table->decimal('new_total_cost', 20, 4)->default(0);
                $table->decimal('waste_qty', 18, 6)->default(0);
                $table->decimal('waste_cost', 20, 4)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['category_id', 'created_at']);
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_cost_histories');

        Schema::table('processing_receipt_lines', function (Blueprint $table) {
            foreach ([
                'cost_apply_mode',
                'suggested_unit_cost',
                'prior_unit_cost',
                'prior_qty',
                'resulting_unit_cost',
            ] as $col) {
                if (Schema::hasColumn('processing_receipt_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
