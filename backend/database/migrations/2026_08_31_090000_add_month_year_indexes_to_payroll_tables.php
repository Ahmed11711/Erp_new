<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فهارس لتسريع eager loading في كشف المرتبات وكشف الحساب.
     *
     * @var array<string, string>
     */
    private array $targets = [
        'employee_merits' => 'employee_merits_emp_month_year_index',
        'employee_subtractions' => 'employee_subtractions_emp_month_year_index',
        'employee_advance_payments' => 'employee_advance_payments_emp_month_year_index',
    ];

    public function up(): void
    {
        foreach ($this->targets as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if ($this->indexExists($tableName, $indexName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->index(['employee_id', 'month', 'year'], $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! $this->indexExists($tableName, $indexName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = ?", [$indexName]))
            ->isNotEmpty();
    }
};
