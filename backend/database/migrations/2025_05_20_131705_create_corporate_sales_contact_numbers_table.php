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
        Schema::create('corporate_sales_contact_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('dial_code');
            $table->string('contact_number');
            $table->foreignId('corporate_sales_contact_id');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->foreign('corporate_sales_contact_id', 'cs_contact_id_fk')
                ->references('id')->on('corporate_sales_contacts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corporate_sales_contact_numbers');
    }
};
