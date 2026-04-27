<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ربط سطر فاتورة المشتريات بصنف واحد (id) لتجنب تحديث كل الصفوف ذات الاسم في كل المخازن.
     */
    public function up(): void
    {
        Schema::table('invoice_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_categories', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('purchase_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_categories', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_categories', 'category_id')) {
                $table->dropColumn('category_id');
            }
        });
    }
};
