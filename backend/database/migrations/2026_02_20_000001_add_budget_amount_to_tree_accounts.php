<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('tree_accounts', 'budget_amount')) {
                $table->decimal('budget_amount', 15, 2)->nullable()->after('budget_type');
            }
            if (!Schema::hasColumn('tree_accounts', 'budget_period')) {
                $table->string('budget_period')->nullable()->after('budget_amount'); // 'yearly', 'monthly', null
            }
        });
    }

    public function down()
    {
        Schema::table('tree_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tree_accounts', 'budget_amount')) {
                $table->dropColumn('budget_amount');
            }
            if (Schema::hasColumn('tree_accounts', 'budget_period')) {
                $table->dropColumn('budget_period');
            }
        });
    }
};
