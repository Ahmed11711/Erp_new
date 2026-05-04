<?php

namespace App\Services\Manufacturing;

use App\Models\Stock;

final class ProductionWarehouseResolver
{
    public static function rawMaterialsStock(): ?Stock
    {
        return Stock::query()->where('warehouse_type', 'raw_materials')->first()
            ?? Stock::query()->where('name', 'مخزن مواد خام')->first();
    }

    public static function wipStock(): ?Stock
    {
        return Stock::query()->where('warehouse_type', 'wip')->first()
            ?? Stock::query()->where('name', 'مخزن منتج تحت التشغيل')->first();
    }

    public static function finishedGoodsStock(): ?Stock
    {
        return Stock::query()->where('warehouse_type', 'finished_goods')->first()
            ?? Stock::query()->where('name', 'مخزن منتج تام')->first();
    }
}
