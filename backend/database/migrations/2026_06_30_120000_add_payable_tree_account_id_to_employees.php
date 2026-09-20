<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'payable_tree_account_id')) {
                $table->foreignId('payable_tree_account_id')
                    ->nullable()
                    ->after('acc_no')
                    ->constrained('tree_accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'payable_tree_account_id')) {
                $table->dropForeign(['payable_tree_account_id']);
                $table->dropColumn('payable_tree_account_id');
            }
        });
    }
};
