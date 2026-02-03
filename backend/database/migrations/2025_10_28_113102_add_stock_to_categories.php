<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

            // ✅ تنظيف التواريخ غير الصالحة قبل أي ALTER TABLE
            DB::table('categories')
                ->where('created_at', '0000-00-00 00:00:00')
                ->update(['created_at' => null]);

            DB::table('categories')
                ->where('updated_at', '0000-00-00 00:00:00')
                ->update(['updated_at' => null]);

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
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }

                $table->dropColumn('stock_id');
            });
        }
    }
};
