<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'shopify_reviewed_at')) {
                $table->timestamp('shopify_reviewed_at')->nullable()->after('shopify_fulfillment_status');
            }
            if (! Schema::hasColumn('orders', 'shopify_reviewed_by_user_id')) {
                $table->unsignedBigInteger('shopify_reviewed_by_user_id')->nullable()->after('shopify_reviewed_at');
            }
            if (! Schema::hasColumn('orders', 'shopify_review_note')) {
                $table->text('shopify_review_note')->nullable()->after('shopify_reviewed_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['shopify_review_note', 'shopify_reviewed_by_user_id', 'shopify_reviewed_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
