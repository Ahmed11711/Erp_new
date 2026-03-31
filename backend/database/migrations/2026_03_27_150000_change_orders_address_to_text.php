<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * عناوين الطلبات قد تتجاوز 255 حرفًا (عربي/تفاصيل). TEXT يزيل حد السلسلة القصيرة.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('address')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('address')->change();
        });
    }
};
