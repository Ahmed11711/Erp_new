<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse');
            $table->string('classification_name', 128);
            $table->timestamps();
            $table->unique(['warehouse', 'classification_name'], 'item_classifications_wh_name_unique');
        });

        if (! Schema::hasColumn('categories', 'item_classification_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('item_classification_id')
                    ->nullable()
                    ->after('color')
                    ->constrained('item_classifications')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('categories', 'item_classification')) {
            $pairs = DB::table('categories')
                ->select('warehouse', 'item_classification')
                ->whereNotNull('item_classification')
                ->where('item_classification', '!=', '')
                ->distinct()
                ->get();

            $lookup = [];
            foreach ($pairs as $row) {
                $warehouse = trim((string) $row->warehouse);
                $name = trim((string) $row->item_classification);
                if ($warehouse === '' || $name === '') {
                    continue;
                }
                $key = $warehouse.'|'.$name;
                if (isset($lookup[$key])) {
                    continue;
                }
                $id = DB::table('item_classifications')->insertGetId([
                    'warehouse' => $warehouse,
                    'classification_name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $lookup[$key] = $id;
            }

            $categories = DB::table('categories')
                ->select('id', 'warehouse', 'item_classification')
                ->whereNotNull('item_classification')
                ->where('item_classification', '!=', '')
                ->get();

            foreach ($categories as $cat) {
                $warehouse = trim((string) $cat->warehouse);
                $name = trim((string) $cat->item_classification);
                $key = $warehouse.'|'.$name;
                if (! isset($lookup[$key])) {
                    continue;
                }
                DB::table('categories')
                    ->where('id', $cat->id)
                    ->update(['item_classification_id' => $lookup[$key]]);
            }

            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('item_classification');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'item_classification_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('item_classification_id');
            });
        }

        Schema::dropIfExists('item_classifications');
    }
};
