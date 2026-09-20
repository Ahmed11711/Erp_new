<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private array $targets = [
        'employee_month_paids' => ['employee_month_paids_emp_month_year_index', ['employee_id', 'month', 'year']],
        'employee_subtractions' => ['employee_subtractions_type_status_index', ['type', 'absence_status']],
    ];

    public function up(): void
    {
        foreach ($this->targets as $tableName => [$indexName, $columns]) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $usable = array_values(array_filter($columns, fn ($col) => Schema::hasColumn($tableName, $col)));
            if ($usable === [] || $this->indexExists($tableName, $indexName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($indexName, $usable) {
                $table->index($usable, $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $tableName => [$indexName]) {
            if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
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
