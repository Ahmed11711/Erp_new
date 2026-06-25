<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases_trackings', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases_trackings', 'details')) {
                $table->text('details')->nullable()->after('action');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases_trackings', function (Blueprint $table) {
            if (Schema::hasColumn('purchases_trackings', 'details')) {
                $table->dropColumn('details');
            }
        });
    }
};
