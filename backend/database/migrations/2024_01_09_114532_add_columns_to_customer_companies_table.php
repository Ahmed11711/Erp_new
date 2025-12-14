<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToCustomerCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->integer('number_of_orders')->default(0);
            $table->double('balance')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->dropColumn('number_of_orders');
            $table->dropColumn('balance');
        });
    }
}
