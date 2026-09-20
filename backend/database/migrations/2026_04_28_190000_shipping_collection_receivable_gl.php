<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ذمم شركات التحصيل/الشحن (أصول) — منفصلة عن tree_account_id المستخدم لذمم شحن المورد (خصوم).
     */
    public function up(): void
    {
        Schema::table('shipping_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_companies', 'receivable_tree_account_id')) {
                $table->unsignedBigInteger('receivable_tree_account_id')->nullable()->after('tree_account_id');
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('order_details', 'collection_company_id')) {
                $table->unsignedBigInteger('collection_company_id')->nullable()->after('shipping_company_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'collection_company_id')) {
                $table->dropColumn('collection_company_id');
            }
        });

        Schema::table('shipping_companies', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_companies', 'receivable_tree_account_id')) {
                $table->dropColumn('receivable_tree_account_id');
            }
        });
    }
};
