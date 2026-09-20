<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'employee_merits',
        'employee_subtractions',
        'employee_advance_payments',
        'employee_month_paids',
        'employee_month_accruals',
        'employee_extra_hours',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'employee_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('employee_name')->nullable()->after('employee_id');
                });
            }

            // Backfill names from current employees (best-effort).
            if (Schema::hasColumn($tableName, 'employee_id') && Schema::hasColumn($tableName, 'employee_name')) {
                DB::statement("
                    UPDATE `{$tableName}` AS t
                    INNER JOIN `employees` AS e ON e.id = t.employee_id
                    SET t.employee_name = e.name
                    WHERE t.employee_name IS NULL OR t.employee_name = ''
                ");
            }

            $this->dropEmployeeForeignIfExists($tableName);

            // Orphan rows whose employee was already deleted (no FK historically).
            if (Schema::hasColumn($tableName, 'employee_id')) {
                DB::statement("
                    UPDATE `{$tableName}` AS t
                    LEFT JOIN `employees` AS e ON e.id = t.employee_id
                    SET t.employee_id = NULL
                    WHERE t.employee_id IS NOT NULL AND e.id IS NULL
                ");
            }

            // Allow retaining HR history after the employee row is removed.
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `employee_id` BIGINT UNSIGNED NULL");

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $this->dropEmployeeForeignIfExists($tableName);

            // Orphaned rows (null employee_id) cannot be re-constrained; leave them.
            DB::table($tableName)->whereNull('employee_id')->delete();

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `employee_id` BIGINT UNSIGNED NOT NULL");

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees');
            });

            if (Schema::hasColumn($tableName, 'employee_name')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('employee_name');
                });
            }
        }
    }

    private function dropEmployeeForeignIfExists(string $tableName): void
    {
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'employee_id'
              AND REFERENCED_TABLE_NAME = 'employees'
            GROUP BY CONSTRAINT_NAME
        ", [$tableName]);

        foreach ($fks as $fk) {
            $name = $fk->CONSTRAINT_NAME;
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$name}`");
        }
    }
};
