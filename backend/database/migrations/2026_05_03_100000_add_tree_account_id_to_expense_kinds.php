<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط فئة المصروف بحساب مدين في شجرة الحسابات (مثل أنظمة ERP المحاسبية).
     */
    public function up(): void
    {
        Schema::table('expense_kinds', function (Blueprint $table) {
            $table->foreignId('tree_account_id')
                ->nullable()
                ->after('expense_kind')
                ->constrained('tree_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expense_kinds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tree_account_id');
        });
    }
};
