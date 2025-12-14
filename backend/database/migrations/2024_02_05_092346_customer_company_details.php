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
        Schema::create('customer_company_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_company_id')->constrained('customer_companies');
            $table->string('ref')->nullable();
            $table->string('type')->nullable();
            $table->text('details')->nullable();
            $table->double('amount');
            $table->double('balance_before');
            $table->double('balance_after');
            $table->date('date');
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
        Schema::dropIfExists('customer_company_details');
    }
};
