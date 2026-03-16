<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds payment_type (safe/bank/service_account) and source IDs for expenses.
     */
    public function up()
    {
        if (!Schema::hasColumn('expenses', 'payment_type')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('bank')->after('expense_type');
                $table->unsignedBigInteger('safe_id')->nullable()->after('bank_id');
                $table->unsignedBigInteger('service_account_id')->nullable()->after('safe_id');
            });
        }

        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('safe_id')->references('id')->on('safes')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('service_account_id')->references('id')->on('service_accounts')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }

        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
            });
        } catch (\Exception $e) {
            // FK might already be dropped
        }
        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable()->change();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }

        // Fix orphaned bank_id: set to NULL where bank no longer exists
        DB::table('expenses')
            ->whereNotNull('bank_id')
            ->whereNotIn('bank_id', DB::table('banks')->pluck('id'))
            ->update(['bank_id' => null]);

        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['safe_id']);
            $table->dropForeign(['service_account_id']);
        });
        if (Schema::hasColumn('expenses', 'payment_type')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn(['payment_type', 'safe_id', 'service_account_id']);
            });
        }
        try {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
            });
        } catch (\Exception $e) {}
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('bank_id')->references('id')->on('banks');
        });
    }
};
