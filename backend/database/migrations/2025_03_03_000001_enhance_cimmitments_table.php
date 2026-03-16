<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تحسين جدول الالتزامات وفق أفضل ممارسات المحاسبة:
     * - الجهة المسددة (مورد أو جهة أخرى)
     * - ربط بحسابات شجرة الحسابات
     * - تتبع حالة الالتزام والمدفوع
     */
    public function up()
    {
        Schema::table('cimmitments', function (Blueprint $table) {
            // الجهة المسددة: supplier = مورد، other = جهة أخرى
            if (!Schema::hasColumn('cimmitments', 'payee_type')) {
                $table->string('payee_type', 20)->default('other')->after('name');
            }
            if (!Schema::hasColumn('cimmitments', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('payee_type')
                    ->constrained('suppliers')->nullOnDelete();
            }
            if (!Schema::hasColumn('cimmitments', 'payee_name')) {
                $table->string('payee_name')->nullable()->after('supplier_id')
                    ->comment('اسم الجهة المسددة عند اختيار جهة أخرى');
            }
            // حساب المصروف/التكلفة (مدين عند إنشاء الالتزام)
            if (!Schema::hasColumn('cimmitments', 'expense_account_id')) {
                $table->foreignId('expense_account_id')->nullable()->after('payee_name')
                    ->constrained('tree_accounts')->nullOnDelete();
            }
            // حساب الالتزام (دائن عند إنشاء الالتزام)
            if (!Schema::hasColumn('cimmitments', 'liability_account_id')) {
                $table->foreignId('liability_account_id')->nullable()->after('expense_account_id')
                    ->constrained('tree_accounts')->nullOnDelete();
            }
            // الحالة: pending=معلق، partial=مدفوع جزئياً، paid=مدفوع بالكامل
            if (!Schema::hasColumn('cimmitments', 'status')) {
                $table->string('status', 20)->default('pending')->after('deserved_amount');
            }
            if (!Schema::hasColumn('cimmitments', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('status');
            }
            if (!Schema::hasColumn('cimmitments', 'notes')) {
                $table->text('notes')->nullable()->after('paid_amount');
            }
        });
    }

    public function down()
    {
        Schema::table('cimmitments', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['expense_account_id']);
            $table->dropForeign(['liability_account_id']);
            $table->dropColumn(['payee_type', 'supplier_id', 'payee_name', 'expense_account_id', 
                'liability_account_id', 'status', 'paid_amount', 'notes']);
        });
    }
};
