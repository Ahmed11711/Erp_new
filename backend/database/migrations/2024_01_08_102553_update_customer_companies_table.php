<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateCustomerCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if the table exists before modifying
        if (Schema::hasTable('customer_companies')) {
            // Check if the unique constraint doesn't exist before adding it
            if (!$this->isUniqueConstraintExist('customer_companies', 'name')) {
                Schema::table('customer_companies', function (Blueprint $table) {
                    $table->unique('name');
                });
            }

            // Check if the unique constraint doesn't exist before adding it
            if (!$this->isUniqueConstraintExist('customer_companies', 'phone1')) {
                Schema::table('customer_companies', function (Blueprint $table) {
                    $table->unique('phone1');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customer_companies', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropUnique(['phone1']);
        });
    }

    /**
     * Check if a unique constraint exists on a column.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    private function isUniqueConstraintExist($table, $column)
    {
        $indexName = $table . '_' . $column . '_unique';

        return collect(DB::select("SHOW INDEX FROM $table WHERE Key_name = '$indexName'"))->isNotEmpty();
    }
}
