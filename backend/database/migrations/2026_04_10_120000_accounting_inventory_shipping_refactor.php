<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('purchases', 'product_total')) {
                $table->decimal('product_total', 15, 2)->nullable()->after('total_price');
            }
            if (!Schema::hasColumn('purchases', 'shipping_total')) {
                $table->decimal('shipping_total', 15, 2)->nullable()->after('product_total');
            }
            if (!Schema::hasColumn('purchases', 'grand_total')) {
                $table->decimal('grand_total', 15, 2)->nullable()->after('shipping_total');
            }
        });

        if (Schema::hasTable('purchases')) {
            DB::statement('UPDATE purchases SET product_total = total_price WHERE product_total IS NULL');
            DB::statement('UPDATE purchases SET shipping_total = transport_cost WHERE shipping_total IS NULL');
            DB::statement('UPDATE purchases SET grand_total = COALESCE(product_total,0) + COALESCE(shipping_total,0) WHERE grand_total IS NULL');
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'shipping_revenue')) {
                $table->decimal('shipping_revenue', 15, 2)->nullable()->after('shipping_cost');
            }
            if (!Schema::hasColumn('orders', 'courier_shipping_cost')) {
                $table->decimal('courier_shipping_cost', 15, 2)->nullable()->after('shipping_revenue');
            }
        });

        if (Schema::hasTable('orders')) {
            DB::statement('UPDATE orders SET shipping_revenue = shipping_cost WHERE shipping_revenue IS NULL');
        }

        Schema::table('shipping_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('shipping_companies', 'tree_account_id')) {
                $table->unsignedBigInteger('tree_account_id')->nullable()->after('refused_orders_percentage');
                $table->index('tree_account_id');
            }
        });

        if (!Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('shipping_company_id')->nullable()->constrained('shipping_companies')->nullOnDelete();
                $table->decimal('cost', 15, 2)->default(0);
                $table->string('payment_status', 32)->default('unpaid');
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('daily_entry_id')->nullable();
                $table->unsignedBigInteger('payment_daily_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');

        Schema::table('shipping_companies', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_companies', 'tree_account_id')) {
                $table->dropIndex(['tree_account_id']);
                $table->dropColumn('tree_account_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'shipping_revenue')) {
                $table->dropColumn('shipping_revenue');
            }
            if (Schema::hasColumn('orders', 'courier_shipping_cost')) {
                $table->dropColumn('courier_shipping_cost');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            foreach (['product_total', 'shipping_total', 'grand_total'] as $col) {
                if (Schema::hasColumn('purchases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
