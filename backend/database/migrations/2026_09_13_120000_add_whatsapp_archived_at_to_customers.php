<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('whatsapp_archived_at')->nullable()->after('assigned_agent_id');
            $table->index('whatsapp_archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_archived_at']);
            $table->dropColumn('whatsapp_archived_at');
        });
    }
};
