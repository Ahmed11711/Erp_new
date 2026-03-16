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
        Schema::table('corporate_sales_leads', function (Blueprint $table) {
            $table->foreignId('corporate_sales_industry_id')->nullable()->change();
            $table->string('country_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('corporate_sales_leads', function (Blueprint $table) {
            $table->foreignId('corporate_sales_industry_id')->nullable(false)->change();
            $table->string('country_name')->nullable(false)->change();
        });
    }
};
