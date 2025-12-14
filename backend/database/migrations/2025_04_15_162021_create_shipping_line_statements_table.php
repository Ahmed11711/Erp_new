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
        Schema::create('shipping_line_statements', function (Blueprint $table) {
            $table->id();
            $table->text('date');
            $table->foreignId('shipping_company_id')->constrained('shipping_companies');
            $table->foreignId('order_id')->constrained('orders');
            $table->boolean('canceled')->default(false);
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
        Schema::dropIfExists('shipping_line_statements');
    }
};
