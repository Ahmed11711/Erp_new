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
        if (Schema::hasColumn('banks', 'asset_id')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('asset_id')
                ->nullable()
                ->constrained('tree_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasColumn('banks', 'asset_id')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
