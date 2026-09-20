<?php

namespace App\Services\Inventory;

use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يضمن وجود مخزن مستلزمات التشغيل وحساب المخزون الفرعي المرتبط به.
 */
final class OperatingSuppliesWarehouseResolver
{
    public const WAREHOUSE_NAME = 'مستلزمات تشغيل وأدوات تشغيل';

    public const INVENTORY_ACCOUNT_NAME = 'مخزون مستلزمات التشغيل';

    public const INVENTORY_CODE = '1000225';

    public const OVERHEAD_ACCOUNT_NAME = 'مصروفات صناعية غير مباشرة - مستلزمات تشغيل';

    public const OVERHEAD_CODE = '500017';

    public const WAREHOUSE_TYPE = 'operating_supplies';

    public static function stock(): ?Stock
    {
        if (! Schema::hasTable('stocks')) {
            return null;
        }

        if (Schema::hasColumn('stocks', 'warehouse_type')) {
            $byType = Stock::query()->where('warehouse_type', self::WAREHOUSE_TYPE)->first();
            if ($byType) {
                return $byType;
            }
        }

        return Stock::query()->where('name', self::WAREHOUSE_NAME)->first();
    }

    public static function ensureStock(): Stock
    {
        $existing = self::stock();
        if ($existing) {
            return self::syncStockAccountLink($existing);
        }

        if (! Schema::hasTable('stocks')) {
            throw new \RuntimeException('جدول المخازن غير متوفر.');
        }

        return DB::transaction(function () {
            $assetId = self::ensureInventoryAccountId();
            $payload = [
                'name' => self::WAREHOUSE_NAME,
                'balance' => 0,
                'asset_id' => $assetId,
            ];
            if (Schema::hasColumn('stocks', 'name_en')) {
                $payload['name_en'] = 'Operating supplies & tools warehouse';
            }
            if (Schema::hasColumn('stocks', 'active')) {
                $payload['active'] = true;
            }
            if (Schema::hasColumn('stocks', 'warehouse_type')) {
                $payload['warehouse_type'] = self::WAREHOUSE_TYPE;
            }

            return Stock::query()->create($payload);
        });
    }

    public static function isOperatingSuppliesWarehouse(?Stock $stock, ?string $warehouseName = null): bool
    {
        if ($stock) {
            if (Schema::hasTable('stocks') && Schema::hasColumn('stocks', 'warehouse_type')) {
                $wt = trim((string) ($stock->warehouse_type ?? ''));
                if ($wt === self::WAREHOUSE_TYPE) {
                    return true;
                }
            }
            $name = trim((string) ($stock->name ?? ''));
            if ($name === self::WAREHOUSE_NAME) {
                return true;
            }
        }

        return trim((string) ($warehouseName ?? '')) === self::WAREHOUSE_NAME;
    }

    private static function syncStockAccountLink(Stock $stock): Stock
    {
        $accountId = self::ensureInventoryAccountId();
        $updates = [];

        if ((int) ($stock->asset_id ?? 0) !== $accountId) {
            $updates['asset_id'] = $accountId;
        }
        if (Schema::hasTable('stocks')
            && Schema::hasColumn('stocks', 'warehouse_type')
            && trim((string) ($stock->warehouse_type ?? '')) !== self::WAREHOUSE_TYPE) {
            $updates['warehouse_type'] = self::WAREHOUSE_TYPE;
        }
        if (trim((string) ($stock->name ?? '')) !== self::WAREHOUSE_NAME) {
            $updates['name'] = self::WAREHOUSE_NAME;
        }

        if ($updates !== []) {
            $stock->update($updates);
            $stock->refresh();
        }

        return $stock;
    }

    public static function ensureInventoryAccountId(): int
    {
        if (! Schema::hasTable('tree_accounts')) {
            $fallback = (int) (DB::table('stocks')->value('asset_id') ?? 0);
            if ($fallback > 0) {
                return $fallback;
            }
            throw new \RuntimeException('تعذر تهيئة حساب مخزون مستلزمات التشغيل.');
        }

        $byCode = DB::table('tree_accounts')->where('code', self::INVENTORY_CODE)->first();
        if ($byCode) {
            return (int) $byCode->id;
        }

        $byDetail = DB::table('tree_accounts')
            ->where('detail_type', 'inventory_operating_supplies')
            ->orderBy('id')
            ->first();
        if ($byDetail) {
            return (int) $byDetail->id;
        }

        $inventoryParent = DB::table('tree_accounts')->where('code', '100022')->first()
            ?? DB::table('tree_accounts')
                ->where('type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%مخزون%')->orWhere('name_en', 'like', '%inventory%');
                })
                ->orderBy('id')
                ->first();

        if (! $inventoryParent) {
            throw new \RuntimeException('تعذر تهيئة حساب مخزون مستلزمات التشغيل — لا يوجد حساب أب للمخزون.');
        }

        $row = [
            'name' => self::INVENTORY_ACCOUNT_NAME,
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

        return (int) DB::table('tree_accounts')->insertGetId($row);
    }

    public static function ensureOverheadAccountId(): int
    {
        if (! Schema::hasTable('tree_accounts')) {
            throw new \RuntimeException('تعذر تهيئة حساب مصروفات مستلزمات التشغيل.');
        }

        $byCode = DB::table('tree_accounts')->where('code', self::OVERHEAD_CODE)->first();
        if ($byCode) {
            return (int) $byCode->id;
        }

        $byDetail = DB::table('tree_accounts')
            ->where('detail_type', 'manufacturing_overhead_supplies')
            ->orderBy('id')
            ->first();
        if ($byDetail) {
            return (int) $byDetail->id;
        }

        $expenseParent = DB::table('tree_accounts')->where('code', '50001')->first()
            ?? DB::table('tree_accounts')->where('code', '5000')->first()
            ?? DB::table('tree_accounts')->where('type', 'expense')->orderBy('id')->first();

        if (! $expenseParent) {
            throw new \RuntimeException('تعذر تهيئة حساب مصروفات مستلزمات التشغيل — لا يوجد حساب مصروفات.');
        }

        $row = [
            'name' => self::OVERHEAD_ACCOUNT_NAME,
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

        return (int) DB::table('tree_accounts')->insertGetId($row);
    }
}
