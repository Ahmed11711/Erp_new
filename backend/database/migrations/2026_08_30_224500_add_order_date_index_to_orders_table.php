<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM `orders` WHERE Key_name = ?', ['orders_order_date_index']))
            ->isNotEmpty();
        if ($exists) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->index('order_date');
        });
    }

    public function down(): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM `orders` WHERE Key_name = ?', ['orders_order_date_index']))
            ->isNotEmpty();
        if (! $exists) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['order_date']);
        });
    }
};
