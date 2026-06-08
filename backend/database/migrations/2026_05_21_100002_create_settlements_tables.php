<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 64)->unique();
            $table->string('provider_type', 32);
            $table->unsignedBigInteger('provider_id');
            $table->string('status', 32)->default('draft');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('total_collected', 15, 3)->default(0);
            $table->decimal('total_settled', 15, 3)->default(0);
            $table->decimal('remaining_balance', 15, 3)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('posted_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['provider_type', 'provider_id', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('shipping_company_detail_id')->nullable();
            $table->decimal('order_amount', 15, 3)->default(0);
            $table->decimal('collected_amount', 15, 3)->default(0);
            $table->decimal('settled_amount', 15, 3)->default(0);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->unique(['settlement_id', 'order_id', 'shipping_company_detail_id'], 'settlement_items_unique_line');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlements');
    }
};
