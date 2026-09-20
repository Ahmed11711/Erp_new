<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أصناف مخزن مستلزمات التشغيل لا تتطلب وحدة قياس ولا خط إنتاج.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'production_id')) {
                $table->unsignedBigInteger('production_id')->nullable()->change();
            }
            if (Schema::hasColumn('categories', 'measurement_id')) {
                $table->unsignedBigInteger('measurement_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // لا نُرجع NOT NULL تلقائياً لأن صفوفاً قد تكون null.
    }
};
