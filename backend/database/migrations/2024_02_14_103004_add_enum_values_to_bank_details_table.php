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

        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('bank_details', function (Blueprint $table) {
            $table->enum('type', ['فواتير مشتريات', 'الطلبات', 'المصروفات', 'سداد' , 'تحصيل عملاء شركات' , 'صرف سلف' ,'صرف مرتب']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
