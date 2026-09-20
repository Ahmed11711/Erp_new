<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds formal ERP document audit columns to the legacy stock_movements table.
 *
 * @see 2026_04_26_100000_create_stock_movements_table
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'before_qty')) {
                $table->decimal('before_qty', 18, 6)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('stock_movements', 'after_qty')) {
                $table->decimal('after_qty', 18, 6)->nullable()->after('before_qty');
            }
            if (! Schema::hasColumn('stock_movements', 'movement_type')) {
                $table->string('movement_type', 64)->nullable()->after('direction');
            }
            if (! Schema::hasColumn('stock_movements', 'stock_transaction_id')) {
                $table->foreignId('stock_transaction_id')
                    ->nullable()
                    ->after('reference_id')
                    ->constrained('stock_transactions')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'stock_transaction_id')) {
                $table->dropForeign(['stock_transaction_id']);
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            foreach (['stock_transaction_id', 'movement_type', 'after_qty', 'before_qty'] as $col) {
                if (Schema::hasColumn('stock_movements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
