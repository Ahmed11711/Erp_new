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
        Schema::create('orders_added_new_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_id')->constrained('trackings');
            $table->string('category_name');
            $table->double('old_price');
            $table->double('new_price');
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
        Schema::dropIfExists('orders_added_new_categories');
    }
};
