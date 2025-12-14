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
        Schema::create('income_lists', function (Blueprint $table) {
            $table->id();
            $table->string('month')->unique();

            // Revenue section
            $table->double('sales')->default(0);
            $table->double('sales_returns')->default(0);

            // Opening inventory
            $table->double('opening_raw_materials')->default(0);
            $table->double('opening_under_processing')->default(0);
            $table->double('opening_finished_goods')->default(0);

            // Purchases
            $table->double('opening_storage')->default(0);
            $table->double('purchases')->default(0);
            $table->double('purchase_expenses')->default(0);
            $table->double('purchase_returns')->default(0);

            // Expenses
            $table->double('operating_expenses')->default(0);
            $table->double('sales_expenses')->default(0);

            // Closing inventory
            $table->double('closing_raw_materials')->default(0);
            $table->double('closing_under_processing')->default(0);
            $table->double('closing_finished_goods')->default(0);

            // Total calculations
            $table->double('last_storage')->default(0);
            $table->double('total_cost_of_sales')->default(0);

            // Other income
            $table->double('capital_gains')->default(0);
            $table->double('other_revenues')->default(0);

            // Other expenses
            $table->double('setup_expenses')->default(0);
            $table->double('admin_expenses')->default(0);
            $table->double('depreciation')->default(0);
            $table->double('depreciation_reserves')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('income_lists');
    }
};
