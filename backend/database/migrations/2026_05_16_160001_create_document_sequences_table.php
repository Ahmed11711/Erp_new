<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Locked sequential counters per transaction type (multi-user safe via SELECT ... FOR UPDATE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_type_id')->unique()->constrained('transaction_types')->cascadeOnDelete();
            $table->string('prefix', 16);
            $table->unsignedBigInteger('last_number')->default(1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
