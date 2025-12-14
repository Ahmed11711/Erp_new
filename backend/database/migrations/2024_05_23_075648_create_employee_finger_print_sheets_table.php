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
        Schema::create('employee_finger_print_sheets', function (Blueprint $table) {
            $table->id();
            $table->integer('acc_no');
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('date');
            $table->string('check_in');
            $table->string('check_out');
            $table->string('hours');
            $table->string('hours_permission')->nullable();
            $table->string('iso_date');
            $table->string('time_in');
            $table->string('time_out');
            $table->boolean('vacation')->default(false);
            $table->string('vacation_reason')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->boolean('is_overTime_removed')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_finger_print_sheets');
    }
};
