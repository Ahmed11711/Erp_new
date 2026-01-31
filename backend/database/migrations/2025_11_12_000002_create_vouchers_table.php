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
        if (!Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // التاريخ
            $table->enum('type', ['receipt', 'payment']); // النوع (قبض، دفع)
            $table->enum('voucher_type', ['client', 'supplier']); // نوع السند (عميل، مورد)
            $table->foreignId('account_id')->constrained('tree_accounts')->onDelete('cascade'); // الحساب
            $table->string('client_or_supplier_name')->nullable(); // اسم العميل أو المورد
            $table->foreignId('client_id')->nullable()->constrained('customer_companies')->onDelete('set null'); // العميل
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null'); // المورد
            $table->decimal('amount', 15, 2); // المبلغ
            $table->text('notes')->nullable(); // الملاحظات
            $table->string('reference_number')->nullable(); // الرقم المرجعي
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // المستخدم الذي أنشأ السند
            $table->timestamps();
            
            $table->index('date');
            $table->index('type');
            $table->index('voucher_type');
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
        Schema::dropIfExists('vouchers');
    }
};

