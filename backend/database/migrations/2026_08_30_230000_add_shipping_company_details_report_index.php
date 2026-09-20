<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(DB::select(
            'SHOW INDEX FROM `shipping_company_details` WHERE Key_name = ?',
            ['scd_company_date_status_idx']
        ))->isNotEmpty();
        if ($exists) {
            return;
        }

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->index(
                ['shipping_company_id', 'shipping_date', 'is_done', 'status'],
                'scd_company_date_status_idx'
            );
        });
    }

    public function down(): void
    {
        $exists = collect(DB::select(
            'SHOW INDEX FROM `shipping_company_details` WHERE Key_name = ?',
            ['scd_company_date_status_idx']
        ))->isNotEmpty();
        if (! $exists) {
            return;
        }

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropIndex('scd_company_date_status_idx');
        });
    }
};
