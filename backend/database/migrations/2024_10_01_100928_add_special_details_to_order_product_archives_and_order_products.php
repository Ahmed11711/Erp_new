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
        Schema::table('order_product_archives', function (Blueprint $table) {
            $table->text('special_details')->nullable();
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->text('special_details')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_product_archives', function (Blueprint $table) {
            $table->dropColumn('special_details');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('special_details');
        });
    }
};
