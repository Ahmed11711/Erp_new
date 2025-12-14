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
            $table->text('times')->nullable()->after('is_overTime_removed');
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
            $table->dropColumn('times');
        });
    }
};
