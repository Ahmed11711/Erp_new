<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('capitals', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('target_type'); // 'bank' or 'safe'
            $table->unsignedBigInteger('target_id'); // bank_id or safe_id
            $table->unsignedBigInteger('equity_account_id'); // The Tree Account for Capital/Equity
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('capitals');
    }
};
