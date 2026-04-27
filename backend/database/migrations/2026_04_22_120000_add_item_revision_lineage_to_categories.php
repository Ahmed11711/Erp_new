<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'item_revision')) {
                $table->unsignedInteger('item_revision')->default(1)->after('recipe_id');
            }
            if (! Schema::hasColumn('categories', 'lineage_root_id')) {
                $table->foreignId('lineage_root_id')->nullable()->after('item_revision')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'replaces_item_id')) {
                $table->foreignId('replaces_item_id')->nullable()->after('lineage_root_id')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'replaced_by_item_id')) {
                $table->foreignId('replaced_by_item_id')->nullable()->after('replaces_item_id')->constrained('categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'replaced_by_item_id')) {
                $table->dropForeign(['replaced_by_item_id']);
                $table->dropColumn('replaced_by_item_id');
            }
            if (Schema::hasColumn('categories', 'replaces_item_id')) {
                $table->dropForeign(['replaces_item_id']);
                $table->dropColumn('replaces_item_id');
            }
            if (Schema::hasColumn('categories', 'lineage_root_id')) {
                $table->dropForeign(['lineage_root_id']);
                $table->dropColumn('lineage_root_id');
            }
            if (Schema::hasColumn('categories', 'item_revision')) {
                $table->dropColumn('item_revision');
            }
        });
    }
};
