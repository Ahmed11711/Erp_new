<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tree_accounts')) {
            return;
        }

        Schema::table('tree_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('tree_accounts', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tree_accounts')) {
            return;
        }

        Schema::table('tree_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('tree_accounts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
