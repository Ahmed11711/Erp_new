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
        Schema::create('confirmed_manfuctures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('categories');
            $table->integer('quantity');
            $table->string('status');
            $table->double('total');
            $table->date('date');
            $table->foreignId('user_id')->constrained('users');
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
        Schema::dropIfExists('confirmed_manfuctures');
    }
};
