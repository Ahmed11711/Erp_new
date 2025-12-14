<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_account_id')->constrained('tree_accounts')->onDelete('cascade');
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->string('entry_batch_code')->nullable(); // batch code لو ضفناه في الكود السابق
            $table->timestamps();

            // ===== Indexes =====
            $table->index('tree_account_id'); 
            $table->index('order_id'); 
            $table->index('created_at'); 
            $table->index('entry_batch_code'); // لو هتستخدمه في البحث
        });
    }

    public function down()
    {
        Schema::dropIfExists('account_entries');
    }
};
