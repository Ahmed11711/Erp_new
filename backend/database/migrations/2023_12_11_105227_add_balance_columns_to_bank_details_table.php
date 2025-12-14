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
        // Add two new columns after the 'amount' column
        Schema::table('bank_details', function (Blueprint $table) {
            $table->double('balance_before')->after('amount');
            $table->double('balance_after')->after('balance_before');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse changes and drop the added columns
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('balance_before');
            $table->dropColumn('balance_after');
        });
    }
};
