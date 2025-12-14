<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table ->string('supplier_name');
            $table ->string('supplier_phone');
            $table->string('supplier_address');
            $table->foreignId('supplier_type')->constrained('supplier_types');
            $table->integer('supplier_rate');
            $table->integer('price_rate');
            $table->double('balance');
            $table->double('last_balance');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('suppliers');
    }
};
