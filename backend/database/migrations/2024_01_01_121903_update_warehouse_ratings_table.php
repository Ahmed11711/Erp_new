<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateWarehouseRatingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('warehouse_ratings', function (Blueprint $table) {

            $table->foreignId('invoice_id')->nullable()->constrained('purchases');

            $table->string('ref')->nullable();

            $table->double('fixed_quantity');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('warehouse_ratings', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');

            $table->dropColumn('ref');
            $table->dropColumn('fixed_quantity');

        });
    }
}
