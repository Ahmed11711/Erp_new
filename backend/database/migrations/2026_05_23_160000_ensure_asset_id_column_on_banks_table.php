<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('banks', 'asset_id')) {
            return;
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('asset_id')
                ->nullable()
                ->after('usage')
                ->constrained('tree_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
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
