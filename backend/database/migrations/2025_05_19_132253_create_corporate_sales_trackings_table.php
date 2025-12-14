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
        Schema::create('corporate_sales_trackings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->text('details');
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->foreignId('corporate_sales_lead_id')->constrained('corporate_sales_leads');
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
        Schema::dropIfExists('corporate_sales_trackings');
    }
};
