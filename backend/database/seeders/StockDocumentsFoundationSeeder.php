<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\TransactionType;
use Illuminate\Database\Seeder;

/**
 * Registers formal ERP document types + locked sequences (run after migrations).
 *
 * php artisan db:seed --class=StockDocumentsFoundationSeeder
 */
class StockDocumentsFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Purchase Add', 'code' => 'PURCHASE_ADD', 'prefix' => 'PUR', 'affects_stock' => true, 'stock_direction' => 'in'],
            ['name' => 'Stock Out', 'code' => 'STOCK_OUT', 'prefix' => 'OUT', 'affects_stock' => true, 'stock_direction' => 'out'],
            ['name' => 'Sales Return', 'code' => 'SALES_RETURN', 'prefix' => 'SRT', 'affects_stock' => true, 'stock_direction' => 'in'],
            ['name' => 'Purchase Return', 'code' => 'PURCHASE_RETURN', 'prefix' => 'PRT', 'affects_stock' => true, 'stock_direction' => 'out'],
            ['name' => 'Amanat Out', 'code' => 'AMANAT_OUT', 'prefix' => 'AOU', 'affects_stock' => true, 'stock_direction' => 'out'],
            ['name' => 'Amanat Return', 'code' => 'AMANAT_RETURN', 'prefix' => 'ART', 'affects_stock' => true, 'stock_direction' => 'in'],
            ['name' => 'Warehouse Transfer', 'code' => 'WAREHOUSE_TRANSFER', 'prefix' => 'TRF', 'affects_stock' => true, 'stock_direction' => 'neutral'],
            ['name' => 'Manual Adjustment', 'code' => 'MANUAL_ADJUSTMENT', 'prefix' => 'ADJ', 'affects_stock' => true, 'stock_direction' => 'neutral'],
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
