<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            if (!Schema::hasColumn('tree_accounts', 'name_en')) {
                $table->string('name_en')->nullable()->after('name');
            }
            if (!Schema::hasColumn('tree_accounts', 'account_type')) {
                $table->string('account_type')->nullable()->after('type'); // نوع الحساب (رئيسي، فرعي، مستوى أول)
            }
            if (!Schema::hasColumn('tree_accounts', 'budget_type')) {
                $table->string('budget_type')->nullable()->after('account_type'); // نوع الميزانية
            }
            if (!Schema::hasColumn('tree_accounts', 'is_trading_account')) {
                $table->boolean('is_trading_account')->default(false)->after('budget_type'); // حساب متاجرة
            }
            if (!Schema::hasColumn('tree_accounts', 'debit_balance')) {
                $table->decimal('debit_balance', 15, 2)->default(0)->after('balance'); // رصيد مدين
            }
            if (!Schema::hasColumn('tree_accounts', 'credit_balance')) {
                $table->decimal('credit_balance', 15, 2)->default(0)->after('debit_balance'); // رصيد دائن
            }
            if (!Schema::hasColumn('tree_accounts', 'previous_year_amount')) {
                $table->string('previous_year_amount')->nullable()->after('credit_balance'); // مبلغ سنة ماضية
            }
            if (!Schema::hasColumn('tree_accounts', 'main_account_id')) {
                $table->unsignedBigInteger('main_account_id')->nullable()->after('parent_id'); // حساب رئيسي
            }
        });

        // Add foreign key constraint separately if column was added
        if (Schema::hasColumn('tree_accounts', 'main_account_id')) {
            try {
                Schema::table('tree_accounts', function (Blueprint $table) {
                    $table->foreign('main_account_id')->references('id')->on('tree_accounts')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tree_accounts', 'main_account_id')) {
                try {
                    $table->dropForeign(['main_account_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
            }
            
            $columnsToDrop = [];
            if (Schema::hasColumn('tree_accounts', 'name_en')) {
                $columnsToDrop[] = 'name_en';
            }
            if (Schema::hasColumn('tree_accounts', 'account_type')) {
                $columnsToDrop[] = 'account_type';
            }
            if (Schema::hasColumn('tree_accounts', 'budget_type')) {
                $columnsToDrop[] = 'budget_type';
            }
            if (Schema::hasColumn('tree_accounts', 'is_trading_account')) {
                $columnsToDrop[] = 'is_trading_account';
            }
            if (Schema::hasColumn('tree_accounts', 'debit_balance')) {
                $columnsToDrop[] = 'debit_balance';
            }
            if (Schema::hasColumn('tree_accounts', 'credit_balance')) {
                $columnsToDrop[] = 'credit_balance';
            }
            if (Schema::hasColumn('tree_accounts', 'previous_year_amount')) {
                $columnsToDrop[] = 'previous_year_amount';
            }
            if (Schema::hasColumn('tree_accounts', 'main_account_id')) {
                $columnsToDrop[] = 'main_account_id';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
