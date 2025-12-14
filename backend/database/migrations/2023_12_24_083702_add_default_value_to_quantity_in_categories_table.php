<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultValueToQuantityInCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Specify the table name and column name
        Schema::table('categories', function (Blueprint $table) {
            $table->double('quantity')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // If you want to reverse the default value change, you can add the down logic here
        // Note: This is just an example; you might not need to reverse the default value in this case
    }
}

