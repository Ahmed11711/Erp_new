<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('warehouse_stock_id')
                ->nullable()
                ->constrained('stocks')
                ->nullOnDelete();

            $table->string('warehouse_name', 255);

            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);

            $table->string('reason', 255)->nullable();
            $table->string('performed_by', 255)->nullable();

            $table->timestamps();

            $table->index(['category_id', 'direction']);
            $table->index('warehouse_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
