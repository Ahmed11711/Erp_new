<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الأصول: مصدر الدفع — بنك أو خزينة أو حساب خدمي (مع إبقاء السجلات القديمة كبنك).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('assets', 'payment_source_type')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->string('payment_source_type', 32)->default('bank')->after('payment_amount');
                $table->unsignedBigInteger('safe_id')->nullable()->after('bank_id');
                $table->unsignedBigInteger('service_account_id')->nullable()->after('safe_id');
            });
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable()->change();
            });
        } catch (\Throwable $e) {
            //
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreign('safe_id')->references('id')->on('safes')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreign('service_account_id')->references('id')->on('service_accounts')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('assets', 'payment_source_type')) {
            return;
        }

        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['safe_id']);
            });
        } catch (\Throwable $e) {
            //
        }
        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['service_account_id']);
            });
        } catch (\Throwable $e) {
            //
        }
        try {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
            });
        } catch (\Throwable $e) {
            //
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['payment_source_type', 'safe_id', 'service_account_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->nullable(false)->change();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('bank_id')->references('id')->on('banks');
        });
    }
};
