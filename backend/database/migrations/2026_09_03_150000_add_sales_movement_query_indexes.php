<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_details') || $this->indexExists('order_details', 'order_details_shipping_date_index')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            $table->index('shipping_date', 'order_details_shipping_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_details') || ! $this->indexExists('order_details', 'order_details_shipping_date_index')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropIndex('order_details_shipping_date_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]))
            ->isNotEmpty();
    }
};
