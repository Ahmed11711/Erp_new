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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('invoice_type');
            $table->date('receipt_date');
            $table->double('total_price');
            $table->double('paid_amount');
            $table->double('due_amount');
            $table->double('transport_cost');
            $table->string('price_edited')->default("0");
            $table->string('invoice_image');
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
        Schema::dropIfExists('purchases');
    }
};
