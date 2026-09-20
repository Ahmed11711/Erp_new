<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manufacture_products')) {
            return;
        }

        Schema::table('manufacture_products', function (Blueprint $table) {
            $table->decimal('quantity', 18, 6)->change();
        });

        if (Schema::hasTable('recipes') && Schema::hasTable('recipe_ingredients') && Schema::hasTable('manufactures')) {
            DB::statement('
                UPDATE manufacture_products mp
                INNER JOIN manufactures m ON m.id = mp.manufacture_id
                INNER JOIN recipes r ON r.output_item_id = m.product_id
                INNER JOIN recipe_ingredients ri ON ri.recipe_id = r.id AND ri.item_id = mp.product_id
                SET mp.quantity = ri.quantity,
                    mp.total_price = ROUND(ri.quantity * IFNULL(ri.unit_cost, 0), 4)
            ');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('manufacture_products')) {
            return;
        }

        Schema::table('manufacture_products', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });
    }
};
