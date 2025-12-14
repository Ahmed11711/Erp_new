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
        Schema::create('factory_bank_movements', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('description');
            $table->double('amount_in')->nullable();
            $table->double('amount_out')->nullable();
            $table->double('balance');
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
        Schema::dropIfExists('factory_bank_movements');
    }
};
