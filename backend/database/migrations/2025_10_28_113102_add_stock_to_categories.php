<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('categories') && Schema::hasTable('stocks')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'stock_id')) {
                    $table->foreignId('stock_id')
                        ->nullable()
                        ->constrained('stocks')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'stock_id')) {
            Schema::table('categories', function (Blueprint $table) {
                try {
                    $table->dropForeign(['stock_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
                $table->dropColumn('stock_id');
            });
        }
    }
};
