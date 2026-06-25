<?php

namespace App\Services\Processing;

use App\Models\Stock;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProcessingWarehouseResolver
{
    public static function materialsAtVendorStock(): ?Stock
    {
        return Stock::query()->where('warehouse_type', 'materials_at_vendor')->first()
            ?? Stock::query()->where('name', 'مواد لدى مندوب')->first()
            ?? Stock::query()->where('name', 'مخزون لدى معالج خارجي')->first();
    }

    /**
     * مخزن وسيط لمواد مُصرفة لدى مندوب/مورد — يُنشأ تلقائياً عند أول استخدام.
     */
    public static function ensureMaterialsAtVendorStock(): Stock
    {
        $existing = self::materialsAtVendorStock();
        if ($existing) {
            return self::syncStockAccountLink($existing);
        }

        if (! Schema::hasTable('stocks')) {
            throw new \RuntimeException('جدول المخازن غير متوفر.');
        }

        return DB::transaction(function () {
            $assetId = self::ensureMaterialsAtVendorAccountId();

            return Stock::query()->create([
                'name' => 'مواد لدى مندوب',
                'name_en' => 'Materials with representative',
                'balance' => 0,
                'asset_id' => $assetId,
                'active' => true,
                'warehouse_type' => 'materials_at_vendor',
            ]);
        });
    }

    private static function syncStockAccountLink(Stock $stock): Stock
    {
        $accountId = self::ensureMaterialsAtVendorAccountId();
        $dirty = false;
        $updates = [];

        if ((int) ($stock->asset_id ?? 0) !== $accountId) {
            $updates['asset_id'] = $accountId;
            $dirty = true;
        }
        if (trim((string) ($stock->warehouse_type ?? '')) !== 'materials_at_vendor') {
            $updates['warehouse_type'] = 'materials_at_vendor';
            $dirty = true;
        }

        if ($dirty) {
            $stock->update($updates);
            $stock->refresh();
        }

        return $stock;
    }

    private static function ensureMaterialsAtVendorAccountId(): int
    {
        $byCode = TreeAccount::query()->where('code', '1000224')->first();
        if ($byCode) {
            return (int) $byCode->id;
        }

        $subcontract = TreeAccount::query()
            ->where('detail_type', 'inventory_subcontract')
            ->whereDoesntHave('children')
            ->first();
        if ($subcontract) {
            return (int) $subcontract->id;
        }

        if (! Schema::hasTable('tree_accounts')) {
            $fallback = (int) (Stock::query()->value('asset_id') ?? 0);
            if ($fallback > 0) {
                return $fallback;
            }

            throw new \RuntimeException('تعذر تهيئة حساب مخزون مواد لدى المندوب.');
        }

        $inventoryParent = TreeAccount::query()->where('code', '100022')->first()
            ?? TreeAccount::query()
                ->where('type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%مخزون%')->orWhere('name_en', 'like', '%inventory%');
                })
                ->orderBy('id')
                ->first();

        if (! $inventoryParent) {
            throw new \RuntimeException('تعذر تهيئة حساب مخزون مواد لدى المندوب — لا يوجد حساب أب للمخزون.');
        }

        $account = TreeAccount::query()->create([
            'name' => 'مواد لدى مندوب',
            'name_en' => 'Inventory — Materials with representative',
            'code' => '1000224',
            'type' => 'asset',
            'detail_type' => 'inventory_subcontract',
            'parent_id' => (int) $inventoryParent->id,
            'level' => (int) ($inventoryParent->level ?? 2) + 1,
            'balance' => 0,
        ]);

        return (int) $account->id;
    }
}
