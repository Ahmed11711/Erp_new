<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يسمح بسطور كشف شركة الشحن غير المرتبطة بطلب (سند قبض/صرف مباشر).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_company_details')) {
            return;
        }

        if (Schema::hasColumn('shipping_company_details', 'voucher_id')) {
            return;
        }

        $this->dropForeignKeyIfExists('shipping_company_details', 'order_id');

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('order_id')->constrained('vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipping_company_details') || ! Schema::hasColumn('shipping_company_details', 'voucher_id')) {
            return;
        }

        $this->dropForeignKeyIfExists('shipping_company_details', 'voucher_id');

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->dropColumn('voucher_id');
        });

        $this->dropForeignKeyIfExists('shipping_company_details', 'order_id');

        Schema::table('shipping_company_details', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $constraint = "{$table}_{$column}_foreign";

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }
};
