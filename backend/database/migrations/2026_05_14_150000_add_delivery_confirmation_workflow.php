<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('collection_date');
            $table->unsignedBigInteger('delivered_by_user_id')->nullable()->after('delivery_date');
            $table->string('delivery_batch_code')->nullable()->after('delivered_by_user_id');
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->decimal('collected_amount', 15, 3)->default(0)->after('amount');
            $table->decimal('remaining_amount', 15, 3)->default(0)->after('collected_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['delivery_date', 'delivered_by_user_id', 'delivery_batch_code']);
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropColumn(['collected_amount', 'remaining_amount']);
        });
    }
};
