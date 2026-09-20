<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_dispatch_notes', function (Blueprint $table) {
            $table->string('dispatch_type', 32)->nullable()->after('dispatch_date');
            $table->string('representative_type', 16)->nullable()->after('notes');
            $table->foreignId('internal_user_id')->nullable()->after('representative_type')->constrained('users')->nullOnDelete();
            $table->string('external_representative_name', 255)->nullable()->after('internal_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('processing_dispatch_notes', function (Blueprint $table) {
            $table->dropForeign(['internal_user_id']);
            $table->dropColumn([
                'dispatch_type',
                'representative_type',
                'internal_user_id',
                'external_representative_name',
            ]);
        });
    }
};
