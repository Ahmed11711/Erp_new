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
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('tree_accounts', 'detail_type')) {
                // To distinguish between Cash, Bank, Customer, Supplier, General, etc.
                $table->string('detail_type')->nullable()->after('type'); 
            }
        });

        Schema::table('customer_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_companies', 'tree_account_id')) {
                $table->foreignId('tree_account_id')->nullable()->constrained('tree_accounts')->nullOnDelete();
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'tree_account_id')) {
                $table->foreignId('tree_account_id')->nullable()->constrained('tree_accounts')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tree_accounts', 'detail_type')) {
                $table->dropColumn('detail_type');
            }
        });

        Schema::table('customer_companies', function (Blueprint $table) {
            if (Schema::hasColumn('customer_companies', 'tree_account_id')) {
                $table->dropForeign(['tree_account_id']);
                $table->dropColumn('tree_account_id');
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'tree_account_id')) {
                $table->dropForeign(['tree_account_id']);
                $table->dropColumn('tree_account_id');
            }
        });
    }
};
