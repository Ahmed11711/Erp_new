<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductType;

/**
 * Resolves legacy rows that have no explicit product_type from warehouse name.
 */
final class ProductTypeResolver
{
    public static function fromWarehouseName(string $warehouse): ProductType
    {
        $w = trim($warehouse);
        if ($w === 'مخزن منتج تام') {
            return ProductType::Finished;
        }
        if ($w === 'مخزن منتج تحت التشغيل') {
            return ProductType::SemiFinished;
        }

        return ProductType::RawMaterial;
    }
}
