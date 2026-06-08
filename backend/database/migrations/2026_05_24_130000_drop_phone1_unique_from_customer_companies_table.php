<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_companies')) {
            return;
        }

        if ($this->isUniqueConstraintExist('customer_companies', 'phone1')) {
            Schema::table('customer_companies', function (Blueprint $table) {
                $table->dropUnique(['phone1']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_companies')) {
            return;
        }

        if (!$this->isUniqueConstraintExist('customer_companies', 'phone1')) {
            Schema::table('customer_companies', function (Blueprint $table) {
                $table->unique('phone1');
            });
        }
    }

    private function isUniqueConstraintExist(string $table, string $column): bool
    {
        $indexName = $table . '_' . $column . '_unique';

        return collect(DB::select("SHOW INDEX FROM $table WHERE Key_name = '$indexName'"))->isNotEmpty();
    }
};
