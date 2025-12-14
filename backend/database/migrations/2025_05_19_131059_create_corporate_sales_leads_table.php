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
        Schema::create('corporate_sales_leads', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('company_name');
            $table->text('country_name');
            $table->text('company_facebook');
            $table->text('company_instagram');
            $table->text('company_linkedin');
            $table->text('company_website');
            $table->foreignId('corporate_sales_industry_id')->constrained('corporate_sales_industries');
            $table->foreignId('corporate_sales_lead_source_id')->constrained('corporate_sales_lead_sources');
            $table->foreignId('corporate_sales_lead_tool_id')->constrained('corporate_sales_lead_tools');
            $table->foreignId('user_id')->constrained('users');
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
        Schema::dropIfExists('corporate_sales_leads');
    }
};
