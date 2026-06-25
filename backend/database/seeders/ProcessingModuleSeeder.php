<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\SupplierType;
use App\Models\TransactionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan db:seed --class=ProcessingModuleSeeder
 */
class ProcessingModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSupplierType();
        $this->seedWarehouseAndGl();
        $this->seedTransactionTypes();
    }

    private function seedSupplierType(): void
    {
        SupplierType::query()->firstOrCreate(
            ['supplier_type' => 'معالج خارجي'],
        );
    }

    private function seedWarehouseAndGl(): void
    {
        if (! Schema::hasTable('tree_accounts') || ! Schema::hasTable('stocks')) {
            return;
        }

        $inventoryParent = DB::table('tree_accounts')->where('code', '100022')->first()
            ?? DB::table('tree_accounts')
                ->where('type', 'asset')
                ->where(function ($q) {
                    $q->where('name', 'like', '%مخزون%')->orWhere('name_en', 'like', '%inventory%');
                })
                ->orderBy('id')
                ->first();

        $parentId = $inventoryParent ? (int) $inventoryParent->id : null;
        $level = $inventoryParent ? (int) ($inventoryParent->level ?? 2) + 1 : 3;

        $atVendorAcc = DB::table('tree_accounts')->where('code', '1000224')->first();
        if (! $atVendorAcc && $parentId) {
            DB::table('tree_accounts')->insert([
                'name' => 'مخزون لدى معالج خارجي',
                'name_en' => 'Inventory — Materials at vendor',
                'code' => '1000224',
                'type' => 'asset',
                'detail_type' => 'inventory_subcontract',
                'parent_id' => $parentId,
                'level' => $level,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $atVendorAcc = DB::table('tree_accounts')->where('code', '1000224')->first();
        }

        $expenseParent = DB::table('tree_accounts')->where('code', '50001')->first()
            ?? DB::table('tree_accounts')->where('type', 'expense')->orderBy('id')->first();
        if ($expenseParent && ! DB::table('tree_accounts')->where('code', '500016')->exists()) {
            DB::table('tree_accounts')->insert([
                'name' => 'خسائر تشغيل خارجي (تالف)',
                'name_en' => 'Subcontract scrap / damage',
                'code' => '500016',
                'type' => 'expense',
                'detail_type' => 'subcontract_scrap',
                'parent_id' => $expenseParent->id,
                'level' => (int) ($expenseParent->level ?? 2) + 1,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($expenseParent && ! DB::table('tree_accounts')->where('code', '500017')->exists()) {
            DB::table('tree_accounts')->insert([
                'name' => 'مصاريف تشغيل خارجي',
                'name_en' => 'Subcontract service expense',
                'code' => '500017',
                'type' => 'expense',
                'detail_type' => 'subcontract_service',
                'parent_id' => $expenseParent->id,
                'level' => (int) ($expenseParent->level ?? 2) + 1,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $assetId = $atVendorAcc ? (int) $atVendorAcc->id : (int) (DB::table('stocks')->value('asset_id') ?? 1);
        $stock = DB::table('stocks')->where('warehouse_type', 'materials_at_vendor')->first()
            ?? DB::table('stocks')->where('name', 'مخزون لدى معالج خارجي')->first();

        if (! $stock) {
            DB::table('stocks')->insert([
                'name' => 'مخزون لدى معالج خارجي',
                'name_en' => 'Materials at external processor',
                'balance' => 0,
                'asset_id' => $assetId,
                'active' => true,
                'warehouse_type' => 'materials_at_vendor',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('stocks')->where('id', $stock->id)->update([
                'warehouse_type' => 'materials_at_vendor',
                'asset_id' => $assetId,
                'name_en' => 'Materials at external processor',
                'updated_at' => now(),
            ]);
        }
    }

    private function seedTransactionTypes(): void
    {
        $types = [
            ['name' => 'Processing Order', 'code' => 'PROCESSING_ORDER', 'prefix' => 'SUB', 'affects_stock' => false, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Dispatch', 'code' => 'PROCESSING_DISPATCH', 'prefix' => 'MDN', 'affects_stock' => true, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Receipt', 'code' => 'PROCESSING_RECEIPT', 'prefix' => 'PR', 'affects_stock' => true, 'stock_direction' => 'neutral'],
            ['name' => 'Processing Invoice', 'code' => 'PROCESSING_INVOICE', 'prefix' => 'PVI', 'affects_stock' => false, 'stock_direction' => 'neutral'],
        ];

        foreach ($types as $row) {
            $type = TransactionType::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'prefix' => $row['prefix'],
                    'affects_stock' => $row['affects_stock'],
                    'stock_direction' => $row['stock_direction'],
                    'is_active' => true,
                ]
            );

            DocumentSequence::query()->firstOrCreate(
                ['transaction_type_id' => $type->id],
                ['prefix' => $type->prefix, 'last_number' => 1000]
            );
        }
    }
}
