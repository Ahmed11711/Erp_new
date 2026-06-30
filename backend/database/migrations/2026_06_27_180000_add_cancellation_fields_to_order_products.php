<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (! Schema::hasColumn('order_products', 'cancelled_quantity')) {
                $table->decimal('cancelled_quantity', 12, 3)->default(0)->after('shipped_quantity');
            }
            if (! Schema::hasColumn('order_products', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_quantity');
            }
            if (! Schema::hasColumn('order_products', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            }
            if (! Schema::hasColumn('order_products', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            if (Schema::hasColumn('order_products', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('order_products', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('order_products', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
            if (Schema::hasColumn('order_products', 'cancelled_quantity')) {
                $table->dropColumn('cancelled_quantity');
            }
        });
    }
};
