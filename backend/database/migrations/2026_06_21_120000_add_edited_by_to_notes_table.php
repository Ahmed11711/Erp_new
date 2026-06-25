<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notes', 'edited_by_user_id')) {
            DB::table('notes')
                ->where('created_at', '0000-00-00 00:00:00')
                ->update(['created_at' => now()]);
            DB::table('notes')
                ->where('updated_at', '0000-00-00 00:00:00')
                ->update(['updated_at' => now()]);

            Schema::table('notes', function (Blueprint $table) {
                $table->unsignedBigInteger('edited_by_user_id')->nullable()->after('user_id');
            });

            Schema::table('notes', function (Blueprint $table) {
                $table->foreign('edited_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notes', 'edited_by_user_id')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropForeign(['edited_by_user_id']);
                $table->dropColumn('edited_by_user_id');
            });
        }
    }
};
