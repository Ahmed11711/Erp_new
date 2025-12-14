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
        Schema::table('employee_subtractions', function (Blueprint $table) {
            $table->boolean('reviewed')->default(0);
            $table->enum('absence_status', ['تم باستاذان', 'خصم'])->nullable();
            $table->enum('absence_count', [0,1,2])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_subtractions', function (Blueprint $table) {
            $table->dropColumn('reviewed');
            $table->dropColumn('absence_status');
            $table->dropColumn('absence_count');
        });
    }
};
