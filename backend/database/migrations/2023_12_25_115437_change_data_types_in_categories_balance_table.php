<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeDataTypesInCategoriesBalanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('categories_balance', function (Blueprint $table) {
            $table->double('quantity')->change();
            $table->double('balance_before')->change();
            $table->double('balance_after')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('categories_balance', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('balance_before')->change();
            $table->integer('balance_after')->change();
        });
    }
}
