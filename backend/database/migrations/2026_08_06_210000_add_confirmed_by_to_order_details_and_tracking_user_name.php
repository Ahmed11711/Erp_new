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
            if (! Schema::hasColumn('order_details', 'confirmed_by_user_id')) {
                $table->unsignedBigInteger('confirmed_by_user_id')->nullable()->after('confirm_date');
            }
            if (! Schema::hasColumn('order_details', 'confirmed_by_name')) {
                $table->string('confirmed_by_name')->nullable()->after('confirmed_by_user_id');
            }
        });

        Schema::table('trackings', function (Blueprint $table) {
            if (! Schema::hasColumn('trackings', 'user_name')) {
                $table->string('user_name')->nullable()->after('user_id');
            }
        });

        // Backfill confirmer from the latest «تم تاكيد الطلب» tracking row per order.
        DB::statement("
            UPDATE order_details od
            INNER JOIN (
                SELECT t.order_id, t.user_id, COALESCE(u.name, t.user_name) AS uname
                FROM trackings t
                LEFT JOIN users u ON u.id = t.user_id
                INNER JOIN (
                    SELECT order_id, MAX(id) AS max_id
                    FROM trackings
                    WHERE action = 'تم تاكيد الطلب'
                    GROUP BY order_id
                ) latest ON latest.max_id = t.id
            ) src ON src.order_id = od.order_id
            SET
                od.confirmed_by_user_id = COALESCE(od.confirmed_by_user_id, src.user_id),
                od.confirmed_by_name = COALESCE(od.confirmed_by_name, src.uname)
            WHERE od.confirmed_by_user_id IS NULL OR od.confirmed_by_name IS NULL
        ");

        // Snapshot existing tracking user names where the user still exists.
        DB::statement("
            UPDATE trackings t
            INNER JOIN users u ON u.id = t.user_id
            SET t.user_name = u.name
            WHERE t.user_name IS NULL AND t.user_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            if (Schema::hasColumn('order_details', 'confirmed_by_name')) {
                $table->dropColumn('confirmed_by_name');
            }
            if (Schema::hasColumn('order_details', 'confirmed_by_user_id')) {
                $table->dropColumn('confirmed_by_user_id');
            }
        });

        Schema::table('trackings', function (Blueprint $table) {
            if (Schema::hasColumn('trackings', 'user_name')) {
                $table->dropColumn('user_name');
            }
        });
    }
};
