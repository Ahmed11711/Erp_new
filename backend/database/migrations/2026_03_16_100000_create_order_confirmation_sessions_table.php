<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_confirmation_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('customer_phone', 20)->index();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('flow_state', [
                'pending_postpone_choice',
                'pending_cancel_confirm',
            ]);
            $table->timestamps();

            $table->index(['customer_phone', 'flow_state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_confirmation_sessions');
    }
};
