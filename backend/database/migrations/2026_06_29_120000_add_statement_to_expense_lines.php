<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_lines', 'statement')) {
            Schema::table('expense_lines', function (Blueprint $table) {
                $table->string('statement', 500)->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_lines', 'statement')) {
            Schema::table('expense_lines', function (Blueprint $table) {
                $table->dropColumn('statement');
            });
        }
    }
};
