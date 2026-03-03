<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('purchases', 'payment_type')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('payment_type', 20)->default('bank')->after('bank_id');
                $table->unsignedBigInteger('safe_id')->nullable()->after('payment_type');
                $table->unsignedBigInteger('service_account_id')->nullable()->after('safe_id');
            });
        }

        try {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreign('safe_id')->references('id')->on('safes')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
        try {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreign('service_account_id')->references('id')->on('service_accounts')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }

        try {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
            });
        } catch (\Exception $e) {
            // FK might already be dropped
        }
        $bankIds = DB::table('banks')->pluck('id');
        if ($bankIds->isNotEmpty()) {
            $firstBankId = $bankIds->first();
            DB::table('purchases')->whereNotIn('bank_id', $bankIds->toArray())->update(['bank_id' => $firstBankId]);
        }
        try {
            Schema::table('purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable()->change();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
        try {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            });
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) throw $e;
        }
    }

    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['safe_id']);
            $table->dropForeign(['service_account_id']);
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'safe_id', 'service_account_id']);
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('bank_id')->references('id')->on('banks');
        });
    }
};
