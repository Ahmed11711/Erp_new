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
        if (Schema::hasTable('account_entries')) {
            // First create the columns without foreign keys
            Schema::table('account_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('account_entries', 'voucher_id')) {
                    $table->unsignedBigInteger('voucher_id')->nullable()->after('order_id');
                    $table->index('voucher_id');
                }
                
                if (!Schema::hasColumn('account_entries', 'daily_entry_id')) {
                    $table->unsignedBigInteger('daily_entry_id')->nullable()->after('voucher_id');
                    $table->index('daily_entry_id');
                }
            });

            // Then add foreign keys after tables are created
            Schema::table('account_entries', function (Blueprint $table) {
                if (Schema::hasTable('vouchers') && Schema::hasColumn('account_entries', 'voucher_id')) {
                    try {
                        $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('set null');
                    } catch (\Exception $e) {
                        // Foreign key might already exist, ignore
                    }
                }
                
                if (Schema::hasTable('daily_entries') && Schema::hasColumn('account_entries', 'daily_entry_id')) {
                    try {
                        $table->foreign('daily_entry_id')->references('id')->on('daily_entries')->onDelete('set null');
                    } catch (\Exception $e) {
                        // Foreign key might already exist, ignore
                    }
                }
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
        Schema::table('account_entries', function (Blueprint $table) {
            if (Schema::hasColumn('account_entries', 'voucher_id')) {
                $table->dropForeign(['voucher_id']);
                $table->dropColumn('voucher_id');
            }
            
            if (Schema::hasColumn('account_entries', 'daily_entry_id')) {
                $table->dropForeign(['daily_entry_id']);
                $table->dropColumn('daily_entry_id');
            }
        });
    }
};
