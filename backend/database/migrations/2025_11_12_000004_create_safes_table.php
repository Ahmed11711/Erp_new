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
        if (!Schema::hasTable('safes')) {
            Schema::create('safes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الخزينة
            $table->decimal('balance', 15, 2)->default(0); // رصيد الخزينة
            $table->enum('type', ['main', 'branch']); // نوع الخزينة (رئيسية، فرع)
            $table->boolean('is_inside_branch')->default(false); // هل توجد داخل فرع
            $table->string('branch_name')->nullable(); // اسم الفرع
            $table->foreignId('account_id')->nullable()->constrained('tree_accounts')->onDelete('set null'); // الحساب المرتبط
            $table->timestamps();
            
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
        Schema::dropIfExists('safes');
    }
};

