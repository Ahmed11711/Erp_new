<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of operational receivable split (رصيد شركة الشحن vs شركة التحصيل) عند الشحن.
     * عند null يُحسب تلقائياً من prepaid_amount وشركة التحصيل.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('order_details', 'shipping_receivable_amount')) {
                $table->decimal('shipping_receivable_amount', 14, 3)->nullable()->after('collection_company_id');
            }
            if (! Schema::hasColumn('order_details', 'collection_receivable_amount')) {
                $table->decimal('collection_receivable_amount', 14, 3)->nullable()->after('shipping_receivable_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'collection_receivable_amount')) {
                $table->dropColumn('collection_receivable_amount');
            }
            if (Schema::hasColumn('order_details', 'shipping_receivable_amount')) {
                $table->dropColumn('shipping_receivable_amount');
            }
        });
    }
};
