<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ملاحظات وبيان المصروف قد تتجاوز 255 حرفاً (VARCHAR الافتراضي لـ string()).
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->text('note')->change();
            $table->text('expens_statement')->change();
            $table->text('address')->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('note')->change();
            $table->string('expens_statement')->change();
            $table->string('address')->change();
        });
    }
};
