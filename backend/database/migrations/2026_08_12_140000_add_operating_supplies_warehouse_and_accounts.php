<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مخزن «مستلزمات تشغيل وأدوات تشغيل» + حساب مخزون فرعي تحت المخزون
 * + حساب مصروفات صناعية غير مباشرة (مستلزمات تشغيل) لصرف المخزن على التشغيل.
 */
return new class extends Migration
{
    public const WAREHOUSE_NAME = 'مستلزمات تشغيل وأدوات تشغيل';

    public const INVENTORY_CODE = '1000225';

    public const OVERHEAD_CODE = '500017';

    public function up(): void
    {
        if (! Schema::hasTable('tree_accounts') || ! Schema::hasTable('stocks')) {
            return;
        }

        if (! Schema::hasColumn('stocks', 'warehouse_type')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->string('warehouse_type', 64)->nullable()->after('name');
            });
        }
        if (! Schema::hasColumn('stocks', 'name_en')) {
            Schema::table('stocks', function (Blueprint $table) {
                $table->string('name_en')->nullable()->after('name');
            });
        }

        $inventoryParent = DB::table('tree_accounts')->where('code', '100022')->first()
            ?? DB::table('tree_accounts')
                ->where('type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%مخزون%')->orWhere('name_en', 'like', '%inventory%');
                })
                ->orderBy('id')
                ->first();

        $inventoryAccountId = null;
        if ($inventoryParent) {
            $existing = DB::table('tree_accounts')->where('code', self::INVENTORY_CODE)->first()
                ?? DB::table('tree_accounts')->where('detail_type', 'inventory_operating_supplies')->first();

            if ($existing) {
                DB::table('tree_accounts')->where('id', $existing->id)->update([
                    'name' => 'مخزون مستلزمات التشغيل',
                    'name_en' => 'Inventory — Operating supplies',
                    'detail_type' => 'inventory_operating_supplies',
                    'updated_at' => now(),
                ]);
                $inventoryAccountId = (int) $existing->id;
            } else {
                $row = [
                    'name' => 'مخزون مستلزمات التشغيل',
                    'name_en' => 'Inventory — Operating supplies',
                    'code' => self::INVENTORY_CODE,
                    'type' => 'asset',
                    'detail_type' => 'inventory_operating_supplies',
                    'parent_id' => (int) $inventoryParent->id,
                    'level' => (int) ($inventoryParent->level ?? 2) + 1,
                    'balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('tree_accounts', 'debit_balance')) {
                    $row['debit_balance'] = 0;
                    $row['credit_balance'] = 0;
                }
                $inventoryAccountId = (int) DB::table('tree_accounts')->insertGetId($row);
            }
        }

        $expenseParent = DB::table('tree_accounts')->where('code', '50001')->first()
            ?? DB::table('tree_accounts')->where('code', '5000')->first()
            ?? DB::table('tree_accounts')->where('type', 'expense')->orderBy('id')->first();

        if ($expenseParent) {
            $overhead = DB::table('tree_accounts')->where('code', self::OVERHEAD_CODE)->first()
                ?? DB::table('tree_accounts')->where('detail_type', 'manufacturing_overhead_supplies')->first();

            if ($overhead) {
                DB::table('tree_accounts')->where('id', $overhead->id)->update([
                    'name' => 'مصروفات صناعية غير مباشرة - مستلزمات تشغيل',
                    'name_en' => 'Manufacturing overhead — Operating supplies',
                    'detail_type' => 'manufacturing_overhead_supplies',
                    'updated_at' => now(),
                ]);
            } else {
                $row = [
                    'name' => 'مصروفات صناعية غير مباشرة - مستلزمات تشغيل',
                    'name_en' => 'Manufacturing overhead — Operating supplies',
                    'code' => self::OVERHEAD_CODE,
                    'type' => 'expense',
                    'detail_type' => 'manufacturing_overhead_supplies',
                    'parent_id' => (int) $expenseParent->id,
                    'level' => (int) ($expenseParent->level ?? 2) + 1,
                    'balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('tree_accounts', 'debit_balance')) {
                    $row['debit_balance'] = 0;
                    $row['credit_balance'] = 0;
                }
                DB::table('tree_accounts')->insert($row);
            }
        }

        $assetId = $inventoryAccountId
            ?: (int) (DB::table('stocks')->value('asset_id') ?? 0);
        if ($assetId <= 0) {
            $assetId = (int) (DB::table('tree_accounts')->where('type', 'asset')->orderBy('id')->value('id') ?? 1);
        }

        $stock = DB::table('stocks')->where('name', self::WAREHOUSE_NAME)->first();
        if (! $stock) {
            $payload = [
                'name' => self::WAREHOUSE_NAME,
                'balance' => 0,
                'asset_id' => $assetId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('stocks', 'name_en')) {
                $payload['name_en'] = 'Operating supplies & tools warehouse';
            }
            if (Schema::hasColumn('stocks', 'warehouse_type')) {
                $payload['warehouse_type'] = 'operating_supplies';
            }
            if (Schema::hasColumn('stocks', 'active')) {
                $payload['active'] = true;
            }
            DB::table('stocks')->insert($payload);
        } else {
            $updates = [];
            if ((int) ($stock->asset_id ?? 0) !== $assetId && $assetId > 0) {
                $updates['asset_id'] = $assetId;
            }
            if (Schema::hasColumn('stocks', 'warehouse_type')
                && trim((string) ($stock->warehouse_type ?? '')) !== 'operating_supplies') {
                $updates['warehouse_type'] = 'operating_supplies';
            }
            if (Schema::hasColumn('stocks', 'name_en') && trim((string) ($stock->name_en ?? '')) === '') {
                $updates['name_en'] = 'Operating supplies & tools warehouse';
            }
            if ($updates !== []) {
                $updates['updated_at'] = now();
                DB::table('stocks')->where('id', $stock->id)->update($updates);
            }
        }

        if (Schema::hasTable('stock_transactions')
            && Schema::hasTable('productions')
            && ! Schema::hasColumn('stock_transactions', 'production_id')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->foreignId('production_id')->nullable()->after('warehouse_id')
                    ->constrained('productions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transactions') && Schema::hasColumn('stock_transactions', 'production_id')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('production_id');
            });
        }
    }
};
