<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('order_details', 'shipping_provider_id')) {
                $table->unsignedBigInteger('shipping_provider_id')->nullable()->after('shipping_company_id');
            }
            if (! Schema::hasColumn('order_details', 'collection_provider_type')) {
                $table->string('collection_provider_type', 32)->nullable()->after('collection_company_id');
            }
            if (! Schema::hasColumn('order_details', 'collection_provider_id')) {
                $table->unsignedBigInteger('collection_provider_id')->nullable()->after('collection_provider_type');
            }
            if (! Schema::hasColumn('order_details', 'total_amount')) {
                $table->decimal('total_amount', 15, 3)->nullable()->after('collection_receivable_amount');
            }
            if (! Schema::hasColumn('order_details', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 3)->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('order_details', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 3)->nullable()->after('paid_amount');
            }
            if (! Schema::hasColumn('order_details', 'amount_to_collect')) {
                $table->decimal('amount_to_collect', 15, 3)->nullable()->after('remaining_amount');
            }
            if (! Schema::hasColumn('order_details', 'delivery_status')) {
                $table->string('delivery_status', 32)->default('pending')->after('amount_to_collect');
            }
            if (! Schema::hasColumn('order_details', 'collection_status')) {
                $table->string('collection_status', 32)->default('pending')->after('delivery_status');
            }
            if (! Schema::hasColumn('order_details', 'settlement_status')) {
                $table->string('settlement_status', 32)->default('open')->after('collection_status');
            }
            if (! Schema::hasColumn('order_details', 'liability_holder_type')) {
                $table->string('liability_holder_type', 32)->nullable()->after('settlement_status');
            }
            if (! Schema::hasColumn('order_details', 'liability_holder_id')) {
                $table->unsignedBigInteger('liability_holder_id')->nullable()->after('liability_holder_type');
            }
            if (! Schema::hasColumn('order_details', 'liability_transferred_at')) {
                $table->timestamp('liability_transferred_at')->nullable()->after('liability_holder_id');
            }
        });

        // Backfill shipping_provider_id from shipping_company_id
        if (Schema::hasColumn('order_details', 'shipping_company_id')) {
            DB::table('order_details')
                ->whereNotNull('shipping_company_id')
                ->whereNull('shipping_provider_id')
                ->update([
                    'shipping_provider_id' => DB::raw('shipping_company_id'),
                ]);
        }

        // Legacy collection_company_id → polymorphic shipping_company provider
        if (Schema::hasColumn('order_details', 'collection_company_id')) {
            DB::table('order_details')
                ->whereNotNull('collection_company_id')
                ->whereNull('collection_provider_type')
                ->update([
                    'collection_provider_type' => 'shipping_company',
                    'collection_provider_id' => DB::raw('collection_company_id'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $cols = [
                'shipping_provider_id',
                'collection_provider_type',
                'collection_provider_id',
                'total_amount',
                'paid_amount',
                'remaining_amount',
                'amount_to_collect',
                'delivery_status',
                'collection_status',
                'settlement_status',
                'liability_holder_type',
                'liability_holder_id',
                'liability_transferred_at',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('order_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
