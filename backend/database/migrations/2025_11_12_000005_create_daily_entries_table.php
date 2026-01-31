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
        if (!Schema::hasTable('daily_entries')) {
            Schema::create('daily_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // التاريخ
            $table->string('entry_number')->unique(); // رقم القيد
            $table->text('description')->nullable(); // الوصف العام
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المستخدم الذي أنشأ القيد
            $table->timestamps();
            
            $table->index('date');
            $table->index('entry_number');
        });
        }

        if (!Schema::hasTable('daily_entry_items')) {
            Schema::create('daily_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_entry_id')->constrained('daily_entries')->onDelete('cascade'); // القيد اليومي
            $table->foreignId('account_id')->constrained('tree_accounts')->onDelete('cascade'); // الحساب
            $table->decimal('debit', 15, 2)->default(0); // مدين
            $table->decimal('credit', 15, 2)->default(0); // دائن
            $table->text('notes')->nullable(); // الملاحظات
            $table->timestamps();
            
            $table->index('daily_entry_id');
            $table->index('account_id');
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
        Schema::dropIfExists('daily_entry_items');
        Schema::dropIfExists('daily_entries');
    }
};

