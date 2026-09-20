<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تمييز دفعات الشحن المسلَّمة حتى يظهر في كشف المندوب ما زال معه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_shipments')) {
            return;
        }

        Schema::table('order_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('order_shipments', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('notes')->index();
            }
            if (! Schema::hasColumn('order_shipments', 'delivered_by')) {
                $table->unsignedBigInteger('delivered_by')->nullable()->after('delivered_at')->index();
            }
        });

        // طلبات أُغلق تسليمها بالكامل: كل دفعاتها مسلَّمة.
        DB::statement("
            UPDATE order_shipments s
            INNER JOIN orders o ON o.id = s.order_id
            SET s.delivered_at = COALESCE(s.updated_at, s.created_at, NOW())
            WHERE s.delivered_at IS NULL
              AND o.order_status IN ('تم التسليم', 'تم التحصيل')
        ");

        // تسليم جزئي: الدفعات حتى تاريخ التسليم تُعد مسلَّمة، وما بعدها يبقى مع المندوب.
        DB::statement("
            UPDATE order_shipments s
            INNER JOIN orders o ON o.id = s.order_id
            INNER JOIN order_details d ON d.order_id = o.id
            SET s.delivered_at = COALESCE(CONCAT(d.delivery_date, ' 12:00:00'), s.updated_at, NOW())
            WHERE s.delivered_at IS NULL
              AND o.order_status = 'تسليم جزئي'
              AND d.delivery_date IS NOT NULL
              AND s.shipped_at IS NOT NULL
              AND s.shipped_at <= d.delivery_date
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_shipments')) {
            return;
        }

        Schema::table('order_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('order_shipments', 'delivered_by')) {
                $table->dropColumn('delivered_by');
            }
            if (Schema::hasColumn('order_shipments', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
        });
    }
};
