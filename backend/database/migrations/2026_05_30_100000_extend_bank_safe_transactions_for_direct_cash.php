<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_transactions')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('bank_transactions', 'counter_account_id')) {
                    $table->foreignId('counter_account_id')
                        ->nullable()
                        ->after('to_safe_id')
                        ->constrained('tree_accounts')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('bank_transactions', 'entry_batch_code')) {
                    $table->string('entry_batch_code', 64)->nullable()->after('notes');
                    $table->index('entry_batch_code');
                }
            });
        }

        if (Schema::hasTable('safe_transactions')) {
            Schema::table('safe_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('safe_transactions', 'counter_account_id')) {
                    $table->foreignId('counter_account_id')
                        ->nullable()
                        ->after('to_safe_id')
                        ->constrained('tree_accounts')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('safe_transactions', 'entry_batch_code')) {
                    $table->string('entry_batch_code', 64)->nullable()->after('notes');
                    $table->index('entry_batch_code');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_transactions')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('bank_transactions', 'counter_account_id')) {
                    $table->dropForeign(['counter_account_id']);
                    $table->dropColumn('counter_account_id');
                }
                if (Schema::hasColumn('bank_transactions', 'entry_batch_code')) {
                    $table->dropIndex(['entry_batch_code']);
                    $table->dropColumn('entry_batch_code');
                }
            });
        }

        if (Schema::hasTable('safe_transactions')) {
            Schema::table('safe_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('safe_transactions', 'counter_account_id')) {
                    $table->dropForeign(['counter_account_id']);
                    $table->dropColumn('counter_account_id');
                }
                if (Schema::hasColumn('safe_transactions', 'entry_batch_code')) {
                    $table->dropIndex(['entry_batch_code']);
                    $table->dropColumn('entry_batch_code');
                }
            });
        }
    }
};
