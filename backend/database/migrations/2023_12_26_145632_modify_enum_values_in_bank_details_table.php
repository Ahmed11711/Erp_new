<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyEnumValuesInBankDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the existing 'type' column
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        // Recreate the 'type' column with the updated values
        Schema::table('bank_details', function (Blueprint $table) {
            $table->enum('type', ['فواتير مشتريات', 'الطلبات', 'المصروفات', 'سداد']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse the changes if needed
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
