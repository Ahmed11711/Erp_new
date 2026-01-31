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
        if (!Schema::hasTable('safe_transactions')) {
            Schema::create('safe_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // التاريخ
            $table->enum('type', ['deposit', 'withdrawal', 'transfer']); // النوع (إيداع، سحب، تحويل)
            $table->foreignId('from_safe_id')->nullable()->constrained('safes')->onDelete('cascade'); // من خزينة
            $table->foreignId('to_safe_id')->nullable()->constrained('safes')->onDelete('cascade'); // إلى خزينة
            $table->decimal('amount', 15, 2); // المبلغ
            $table->text('notes')->nullable(); // الملاحظات
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المستخدم
            $table->timestamps();
            
            $table->index('date');
            $table->index('type');
        });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('safe_transactions');
    }
};

