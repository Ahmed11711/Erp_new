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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('expense_type', ['مصروف تشغيل', 'مصروف تسويق', 'مصروف ادارى']);
            $table->foreignId('bank_id')->constrained('banks');
            $table->foreignId('kind_id')->constrained('expense_kinds');
            $table->foreignId('user_id')->constrained('users');
            $table->string('expens_statement');
            $table->double("amount");
            $table->string("note");
            $table->string("address");
            $table->string('expense_image');
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
        Schema::dropIfExists('expenses');
    }
};
