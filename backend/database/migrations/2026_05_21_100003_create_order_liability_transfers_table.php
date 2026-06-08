<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_liability_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_holder_type', 32)->default('customer');
            $table->unsignedBigInteger('from_holder_id')->nullable();
            $table->string('to_holder_type', 32);
            $table->unsignedBigInteger('to_holder_id');
            $table->decimal('amount', 15, 3);
            $table->string('reason', 64)->nullable();
            $table->string('gl_batch_code', 128)->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_liability_transfers');
    }
};
