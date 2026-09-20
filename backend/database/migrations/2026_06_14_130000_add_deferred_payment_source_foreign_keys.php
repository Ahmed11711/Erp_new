<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds payment-source foreign keys that early migrations could not create
 * because safes/service_accounts tables did not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addForeignKeyIfMissing('purchases', 'safe_id', 'safes');
        $this->addForeignKeyIfMissing('purchases', 'service_account_id', 'service_accounts');
        $this->addForeignKeyIfMissing('expenses', 'safe_id', 'safes');
        $this->addForeignKeyIfMissing('expenses', 'service_account_id', 'service_accounts');
        $this->addForeignKeyIfMissing('supplier_pays', 'safe_id', 'safes');
        $this->addForeignKeyIfMissing('supplier_pays', 'service_account_id', 'service_accounts');
        $this->addForeignKeyIfMissing('cimmitments', 'expense_account_id', 'tree_accounts');
        $this->addForeignKeyIfMissing('cimmitments', 'liability_account_id', 'tree_accounts');
    }

    public function down(): void
    {
        foreach (['purchases', 'expenses', 'supplier_pays'] as $table) {
            $this->dropForeignKeyIfExists($table, 'safe_id');
            $this->dropForeignKeyIfExists($table, 'service_account_id');
        }
        foreach (['cimmitments'] as $table) {
            $this->dropForeignKeyIfExists($table, 'expense_account_id');
            $this->dropForeignKeyIfExists($table, 'liability_account_id');
        }
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $referencedTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($referencedTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $column) || $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $referencedTable) {
            $table->foreign($column)->references('id')->on($referencedTable)->nullOnDelete();
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
