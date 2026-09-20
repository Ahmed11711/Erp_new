<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('confirmed_manfuctures', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users');
            $table->json('completion_meta')->nullable()->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('confirmed_manfuctures', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'completion_meta']);
        });
    }
};
