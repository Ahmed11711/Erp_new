<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('stock_id')->nullable()->constrained('stocks')->nullOnDelete();
            $table->decimal('quantity', 18, 6)->default(0);
            $table->decimal('cost_value', 22, 4)->default(0);
            $table->timestamps();

            $table->unique('category_id');
            $table->index(['stock_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->foreignId('stock_id')->nullable()->constrained('stocks')->nullOnDelete();

            $table->string('warehouse_name', 255)->nullable();

            $table->enum('direction', ['in', 'out']);

            /** @see \App\Enums\InventoryMovementType */
            $table->string('movement_type', 64);

            $table->decimal('quantity', 18, 6);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_cost', 20, 4)->nullable();

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);

            $table->foreignId('daily_entry_id')->nullable()->constrained('daily_entries')->nullOnDelete();

            $table->string('reason', 512)->nullable();
            $table->string('performed_by', 255)->nullable();

            $table->timestamps();

            $table->index(['category_id', 'direction']);
            $table->index(['category_id', 'movement_type']);
            $table->index('movement_type');
        });

        if (Schema::hasTable('stock_movements') && Schema::hasTable('inventory_movements')) {
            try {
                DB::statement('INSERT INTO inventory_movements (
                    category_id, stock_id, warehouse_name, direction, movement_type,
                    quantity, unit_cost, total_cost, reference_type, reference_id,
                    daily_entry_id, reason, performed_by, created_at, updated_at
                )
                SELECT
                    sm.category_id,
                    sm.warehouse_stock_id,
                    sm.warehouse_name,
                    sm.direction,
                    \'legacy_stock_movement\',
                    sm.quantity,
                    sm.unit_cost,
                    sm.total_cost,
                    sm.reference_type,
                    sm.reference_id,
                    NULL,
                    sm.reason,
                    sm.performed_by,
                    sm.created_at,
                    sm.updated_at
                FROM stock_movements sm');
            } catch (\Throwable $e) {
                // ignore copy failures (empty / incompatible)
            }
        }

        Schema::table('stocks', function (Blueprint $table) {
            if (! Schema::hasColumn('stocks', 'warehouse_type')) {
                $table->string('warehouse_type', 32)->nullable()->after('active');
            }
            if (! Schema::hasColumn('stocks', 'name_en')) {
                $table->string('name_en', 191)->nullable()->after('name');
            }
        });

        $this->seedWarehouseTypesAndAccounts();

        $this->backfillInventoryBalancesFromCategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');

        Schema::table('stocks', function (Blueprint $table) {
            if (Schema::hasColumn('stocks', 'warehouse_type')) {
                $table->dropColumn('warehouse_type');
            }
            if (Schema::hasColumn('stocks', 'name_en')) {
                $table->dropColumn('name_en');
            }
        });
    }

    private function seedWarehouseTypesAndAccounts(): void
    {
        $inventoryParent = DB::table('tree_accounts')->where('code', '100022')->first();

        if (! $inventoryParent) {
            $inventoryParent = DB::table('tree_accounts')
                ->where('type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%مخزون%')->orWhere('name_en', 'like', '%inventory%');
                })
                ->orderBy('id')
                ->first();
        }

        $parentId = $inventoryParent ? (int) $inventoryParent->id : null;
        $level = $inventoryParent ? (int) ($inventoryParent->level ?? 2) + 1 : 3;

        $defs = [
            ['code' => '1000223', 'name' => 'مخزون مواد خام', 'name_en' => 'Inventory — Raw materials', 'detail' => 'inventory_raw', 'type' => 'raw_materials'],
            ['code' => '1000222', 'name' => 'مخزون تحت التشغيل', 'name_en' => 'Inventory — WIP', 'detail' => 'inventory_wip', 'type' => 'wip'],
            ['code' => '1000221', 'name' => 'مخزون منتج تام', 'name_en' => 'Inventory — Finished goods', 'detail' => 'inventory_finished', 'type' => 'finished_goods'],
        ];

        foreach ($defs as $def) {
            $exists = DB::table('tree_accounts')->where('code', $def['code'])->first();
            if (! $exists && $parentId) {
                DB::table('tree_accounts')->insert([
                    'name' => $def['name'],
                    'name_en' => $def['name_en'],
                    'code' => $def['code'],
                    'type' => 'asset',
                    'detail_type' => $def['detail'],
                    'parent_id' => $parentId,
                    'level' => $level,
                    'balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $exists = DB::table('tree_accounts')->where('code', $def['code'])->first();
            } elseif ($exists && ! $exists->detail_type) {
                DB::table('tree_accounts')->where('id', $exists->id)->update([
                    'detail_type' => $def['detail'],
                    'name_en' => $exists->name_en ?: $def['name_en'],
                    'updated_at' => now(),
                ]);
            }

            $assetId = $exists ? (int) $exists->id : null;
            if (! $assetId) {
                continue;
            }

            $map = [
                'raw_materials' => 'مخزن مواد خام',
                'wip' => 'مخزن منتج تحت التشغيل',
                'finished_goods' => 'مخزن منتج تام',
            ];
            $nameAr = $map[$def['type']] ?? null;
            if ($nameAr) {
                $stock = DB::table('stocks')->where('name', $nameAr)->first();
                if ($stock) {
                    DB::table('stocks')->where('id', $stock->id)->update([
                        'warehouse_type' => $def['type'],
                        'asset_id' => $assetId,
                        'name_en' => $def['type'] === 'raw_materials' ? 'Raw materials warehouse' : ($def['type'] === 'wip' ? 'WIP warehouse' : 'Finished goods warehouse'),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $expenseParent = DB::table('tree_accounts')->where('code', '50001')->first()
            ?? DB::table('tree_accounts')->where('type', 'expense')->orderBy('id')->first();
        if ($expenseParent && ! DB::table('tree_accounts')->where('code', '500015')->exists()) {
            DB::table('tree_accounts')->insert([
                'name' => 'فروقات جرد مخزون (عجز)',
                'name_en' => 'Inventory shrinkage (count shortage)',
                'code' => '500015',
                'type' => 'expense',
                'detail_type' => 'inventory_adjustment_loss',
                'parent_id' => $expenseParent->id,
                'level' => (int) ($expenseParent->level ?? 2) + 1,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $revenueParent = DB::table('tree_accounts')->where('code', '40001')->first()
            ?? DB::table('tree_accounts')->where('type', 'revenue')->orderBy('id')->first();
        if ($revenueParent && ! DB::table('tree_accounts')->where('code', '400099')->exists()) {
            DB::table('tree_accounts')->insert([
                'name' => 'فروقات جرد مخزون (زيادة)',
                'name_en' => 'Inventory count surplus',
                'code' => '400099',
                'type' => 'revenue',
                'detail_type' => 'inventory_adjustment_gain',
                'parent_id' => $revenueParent->id,
                'level' => (int) ($revenueParent->level ?? 2) + 1,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillInventoryBalancesFromCategories(): void
    {
        $rows = DB::table('categories')->select('id', 'stock_id', 'quantity', 'total_price', 'sell_total_price', 'warehouse')->get();
        foreach ($rows as $row) {
            $qty = (float) ($row->quantity ?? 0);
            $isFg = isset($row->warehouse) && trim((string) $row->warehouse) === 'مخزن منتج تام';
            $costVal = $isFg ? (float) ($row->sell_total_price ?? 0) : (float) ($row->total_price ?? 0);

            DB::table('inventory_balances')->insert([
                'category_id' => $row->id,
                'stock_id' => $row->stock_id,
                'quantity' => $qty,
                'cost_value' => $costVal,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
