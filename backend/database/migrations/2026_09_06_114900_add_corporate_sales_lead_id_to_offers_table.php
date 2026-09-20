<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedBigInteger('corporate_sales_lead_id')->nullable()->after('customer_company_id');
            $table->index('corporate_sales_lead_id');
            $table->foreign('corporate_sales_lead_id')
                ->references('id')
                ->on('corporate_sales_leads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['corporate_sales_lead_id']);
            $table->dropIndex(['corporate_sales_lead_id']);
            $table->dropColumn('corporate_sales_lead_id');
        });
    }
};
