<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (! Schema::hasColumn('offers', 'converted_order_id')) {
                $table->unsignedBigInteger('converted_order_id')->nullable()->after('debt_amount');
            }
            if (! Schema::hasColumn('offers', 'converted_at')) {
                $table->timestamp('converted_at')->nullable()->after('converted_order_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'offer_id')) {
                $table->unsignedBigInteger('offer_id')->nullable()->after('company_id');
                $table->index('offer_id');
            }
            if (! Schema::hasColumn('orders', 'offer_debt_posted')) {
                $table->boolean('offer_debt_posted')->default(false)->after('offer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'converted_at')) {
                $table->dropColumn('converted_at');
            }
            if (Schema::hasColumn('offers', 'converted_order_id')) {
                $table->dropColumn('converted_order_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'offer_debt_posted')) {
                $table->dropColumn('offer_debt_posted');
            }
            if (Schema::hasColumn('orders', 'offer_id')) {
                $table->dropIndex(['offer_id']);
                $table->dropColumn('offer_id');
            }
        });
    }
};
