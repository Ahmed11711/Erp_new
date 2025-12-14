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
        Schema::table('employee_finger_print_sheets', function (Blueprint $table) {
            $table->enum('absence_deduction', ['1' , '1.5', '2', '3'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_finger_print_sheets', function (Blueprint $table) {
            $table->dropColumn('absence_deduction');
        });
    }
};
