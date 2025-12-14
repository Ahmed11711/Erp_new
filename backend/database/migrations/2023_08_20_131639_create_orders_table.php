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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_type');
            $table->string('customer_phone_1');
            $table->string('customer_phone_2');
            $table->string('governorate');
            $table->string('city')->nullable();
            $table->string('address');
            $table->date('order_date');
            $table->foreignId('shipping_method_id')->constrained('shipping_methods');
            $table->foreignId('order_source_id')->constrained('order_sources');
            $table->string('order_status')->default('طلب جديد');
            $table->string('order_notes')->nullable();
            $table->string('order_image')->nullable();
            $table->string('order_type');
            $table->double('shipping_cost')->default(0);
            $table->double('total_invoice');
            $table->double('prepaid_amount')->default(0);
            $table->double('discount')->default(0);
            $table->double('net_total')->default(0);
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
        Schema::dropIfExists('orders');
    }
};
