<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('sort_order');
        });

        $oldTitle = 'Price list for summer 26';
        if (Schema::hasTable('price_list_settings')) {
            $oldTitle = (string) (DB::table('price_list_settings')->orderBy('id')->value('title') ?: $oldTitle);
        }

        $listId = (int) DB::table('price_lists')->insertGetId([
            'name' => $oldTitle,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('price_list_items', function (Blueprint $table) {
            $table->unsignedBigInteger('price_list_id')->nullable()->after('id');
        });

        DB::table('price_list_items')->update(['price_list_id' => $listId]);

        $this->dropCodeUniqueIfExists();

        // Make NOT NULL without doctrine/dbal change().
        DB::statement('ALTER TABLE price_list_items MODIFY price_list_id BIGINT UNSIGNED NOT NULL');

        Schema::table('price_list_items', function (Blueprint $table) {
            $table->foreign('price_list_id')->references('id')->on('price_lists')->cascadeOnDelete();
            $table->unique(['price_list_id', 'code']);
        });

        Schema::dropIfExists('price_list_settings');
    }

    public function down(): void
    {
        Schema::create('price_list_settings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Price list for summer 26');
            $table->timestamps();
        });

        $firstName = DB::table('price_lists')->orderBy('id')->value('name') ?: 'Price list for summer 26';
        DB::table('price_list_settings')->insert([
            'title' => $firstName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('price_list_items', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropUnique(['price_list_id', 'code']);
            $table->dropColumn('price_list_id');
            $table->unique('code');
        });

        Schema::dropIfExists('price_lists');
    }

    private function dropCodeUniqueIfExists(): void
    {
        try {
            Schema::table('price_list_items', function (Blueprint $table) {
                $table->dropUnique(['code']);
            });
        } catch (\Throwable $e) {
            try {
                DB::statement('ALTER TABLE price_list_items DROP INDEX price_list_items_code_unique');
            } catch (\Throwable $e2) {
                // ignore
            }
        }
    }
};
