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
        Schema::create('corporate_sales_lead_recommenders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporate_sales_lead_id');
            $table->foreign('corporate_sales_lead_id', 'cs_lead_recommenders_lead_fk')
                ->references('id')->on('corporate_sales_leads')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('corporate_sales_lead_recommenders');
    }
};
