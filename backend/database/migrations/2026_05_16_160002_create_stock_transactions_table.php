<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header for stock documents (mirrors paper slips / internal vouchers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no')->unique();
            $table->foreignId('transaction_type_id')->constrained('transaction_types');
            $table->foreignId('warehouse_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->date('document_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
