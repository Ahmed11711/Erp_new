<?php

namespace Database\Seeders;

use App\Services\Inventory\OperatingSuppliesWarehouseResolver;
use Illuminate\Database\Seeder;

/**
 * php artisan db:seed --class=OperatingSuppliesWarehouseSeeder
 */
class OperatingSuppliesWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $stock = OperatingSuppliesWarehouseResolver::ensureStock();
        $overheadId = OperatingSuppliesWarehouseResolver::ensureOverheadAccountId();

        $this->command?->info(sprintf(
            'مخزن #%d «%s» ↔ حساب مخزون #%d | مصروف مستلزمات #%d',
            (int) $stock->id,
            $stock->name,
            (int) $stock->asset_id,
            $overheadId
        ));
    }
}
