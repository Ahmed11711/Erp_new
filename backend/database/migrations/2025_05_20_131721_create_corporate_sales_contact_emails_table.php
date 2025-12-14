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
        Schema::create('corporate_sales_contact_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->foreignId('corporate_sales_contact_id');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->foreign('corporate_sales_contact_id', 'cs_contact_id_fk_emails')
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
        Schema::dropIfExists('corporate_sales_contact_emails');
    }
};
