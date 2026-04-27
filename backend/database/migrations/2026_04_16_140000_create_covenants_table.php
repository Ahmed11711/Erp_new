<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('covenants', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('covenant_type', 32);
            $table->string('holder_kind', 64);
            $table->string('payment_type', 32);
            $table->unsignedBigInteger('safe_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('service_account_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('covenants');
    }
};
