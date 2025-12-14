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
        Schema::create('expense_kinds', function (Blueprint $table) {
            $table->id();
            $table->enum('expense_type', ['مصروف تشغيل', 'مصروف تسويق', 'مصروف ادارى']);
            $table->string("expense_kind");
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
        Schema::dropIfExists('expense_kinds');
    }
};
