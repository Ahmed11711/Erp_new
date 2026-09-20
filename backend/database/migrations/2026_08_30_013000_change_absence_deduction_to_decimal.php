<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_finger_print_sheets')
            || ! Schema::hasColumn('employee_finger_print_sheets', 'absence_deduction')) {
            return;
        }

        // ENUM + numeric 2 was stored as the 2nd option ('1.5'), not two days.
        DB::statement('ALTER TABLE employee_finger_print_sheets MODIFY absence_deduction DECIMAL(4, 2) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_finger_print_sheets')
            || ! Schema::hasColumn('employee_finger_print_sheets', 'absence_deduction')) {
            return;
        }

        DB::statement("ALTER TABLE employee_finger_print_sheets MODIFY absence_deduction ENUM('1', '1.5', '2', '3') NULL");
    }
};
