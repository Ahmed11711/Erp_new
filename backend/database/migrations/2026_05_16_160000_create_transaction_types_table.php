<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP document / stock transaction type registry (prefix + behaviour flags).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('prefix', 16);
            $table->boolean('affects_stock')->default(true);
            /** in | out | neutral (e.g. transfers use paired lines) */
            $table->string('stock_direction', 16)->default('in');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_types');
    }
};
