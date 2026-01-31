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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('scrap_value', 15, 2)->default(0);
            $table->integer('life_span')->comment('In years')->default(0);
            $table->unsignedBigInteger('asset_account_id')->nullable();
            $table->unsignedBigInteger('depreciation_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();

            // Foreign keys if TreeAccount table uses 'id'
            // $table->foreign('asset_account_id')->references('id')->on('tree_accounts');
            // $table->foreign('depreciation_account_id')->references('id')->on('tree_accounts');
            // $table->foreign('expense_account_id')->references('id')->on('tree_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('assets', function (Blueprint $table) {
            //
        });
    }
};
