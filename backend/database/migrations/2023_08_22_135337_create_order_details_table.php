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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->date('need_by_date')->nullable();;
            $table->foreignId('shipping_company_id')->nullable()->constrained('shipping_companies');
            $table->foreignId('shipping_line_id')->nullable()->constrained('shippinglines');
            $table->integer('edits')->default(0);
            $table->integer('postponed')->default(0);
            $table->boolean('vip')->default(0);
            $table->boolean('shortage')->default(0);
            $table->boolean('reviewed')->default(0);
            $table->string('shippment_image')->nullable();
            $table->string('shippment_number')->nullable();
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
        Schema::dropIfExists('order_details');
    }
};
