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
        Schema::table('order_details', function (Blueprint $table) {
            $table->date('confirm_date')->nullable();
            $table->date('shipping_date')->nullable();
            $table->date('status_date')->nullable();
            $table->date('collection_date')->nullable();
            $table->date('receiving_date')->nullable();
            $table->double('maintenance_cost')->nullable();
            $table->date('maintenance_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('order_details', function (Blueprint $table) {
              $table->dropColumn('confirm_date');
              $table->dropColumn('shipping_date');
              $table->dropColumn('status_date');
              $table->dropColumn('collection_date');
              $table->dropColumn('receiving_date');
              $table->dropColumn('maintenance_cost');
              $table->dropColumn('maintenance_date');
        });
    }
};
