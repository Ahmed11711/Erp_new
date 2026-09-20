<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_lines', 'tree_account_id')) {
            Schema::table('expense_lines', function (Blueprint $table) {
                $table->foreignId('tree_account_id')
                    ->nullable()
                    ->after('kind_id')
                    ->constrained('tree_accounts')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('expenses', 'tree_account_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('tree_account_id')
                    ->nullable()
                    ->after('kind_id')
                    ->constrained('tree_accounts')
                    ->nullOnDelete();
            });
        }

        Schema::table('expense_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('kind_id')->nullable()->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('kind_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_lines', 'tree_account_id')) {
            Schema::table('expense_lines', function (Blueprint $table) {
                $table->dropForeign(['tree_account_id']);
                $table->dropColumn('tree_account_id');
            });
        }

        if (Schema::hasColumn('expenses', 'tree_account_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['tree_account_id']);
                $table->dropColumn('tree_account_id');
            });
        }
    }
};
