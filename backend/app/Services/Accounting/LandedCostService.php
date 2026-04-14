<?php

namespace App\Services\Accounting;

use App\Models\Purchase;

/**
 * تكلفة مبررة للتقارير فقط — دون دمجها في قيد المخزون.
 */
class LandedCostService
{
    /**
     * @return array{product_cost: float, shipping_cost: float, landed_total: float}
     */
    public static function purchaseBreakdown(Purchase $purchase): array
    {
        $product = (float) ($purchase->product_total ?? $purchase->total_price ?? 0);
        $shipping = (float) ($purchase->shipping_total ?? $purchase->transport_cost ?? 0);

        return [
            'product_cost' => round($product, 2),
            'shipping_cost' => round($shipping, 2),
            'landed_total' => round($product + $shipping, 2),
        ];
    }
}
