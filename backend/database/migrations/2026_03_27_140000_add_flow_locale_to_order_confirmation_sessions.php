<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_confirmation_sessions')) {
            return;
        }
        Schema::table('order_confirmation_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('order_confirmation_sessions', 'flow_locale')) {
                $table->string('flow_locale', 8)->default('ar')->after('flow_state');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_confirmation_sessions')) {
            return;
        }
        Schema::table('order_confirmation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('order_confirmation_sessions', 'flow_locale')) {
                $table->dropColumn('flow_locale');
            }
        });
    }
};
