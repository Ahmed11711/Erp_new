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
            $table->text('company_facebook')->nullable()->change();
            $table->text('company_instagram')->nullable()->change();
            $table->text('company_linkedin')->nullable()->change();
            $table->text('company_website')->nullable()->change();
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
            $table->text('company_facebook')->nullable(false)->change();
            $table->text('company_instagram')->nullable(false)->change();
            $table->text('company_linkedin')->nullable(false)->change();
            $table->text('company_website')->nullable(false)->change();
        });
    }
};
