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
        Schema::create('employee_month_paids', function (Blueprint $table) {
            $table->id();
            $table->double('amount');
            $table->foreignId('bank_id')->constrained('banks');
            $table->string('details')->nullable();
            $table->unsignedInteger('month');
            $table->unsignedInteger('year');
            $table->foreignId('employee_id')->constrained('employees');
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
        Schema::dropIfExists('employee_month_paids');
    }
};
