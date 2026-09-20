<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voucher_order_settlement_lines')) {
            return;
        }

        Schema::create('voucher_order_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount_applied', 15, 3);
            $table->timestamps();

            $table->index(['voucher_id', 'order_id'], 'voucher_order_settlement_voucher_order_idx');
            $table->foreign('voucher_id', 'voucher_order_settlement_voucher_fk')
                ->references('id')
                ->on('vouchers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_order_settlement_lines');
    }
};
