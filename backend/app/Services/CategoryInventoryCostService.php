<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * متوسط تكلفة الصنف (weighted average) = total_price / quantity بعد حركات المخزون.
 */
class CategoryInventoryCostService
{
    /**
     * تكلفة مرجعية للوحدة: total_price/qty ثم unit_price ثم متوسط طبقات warehouse_ratings (أسعار الشراء/الدفعات).
     */
    public static function resolveReferenceUnitCost(int $categoryId): float
    {
        $cat = DB::table('categories')->where('id', $categoryId)->first();
        if (! $cat) {
            return 0.0;
        }
        $q = (float) ($cat->quantity ?? 0);
        $tp = (float) ($cat->total_price ?? 0);
        $up = (float) ($cat->unit_price ?? 0);
        if ($q > 0.0000001 && abs($tp) > 0.0000001) {
            return $tp / $q;
        }
        if ($up > 0.0000001) {
            return $up;
        }

        $v = DB::table('warehouse_ratings')
            ->where('category_id', $categoryId)
            ->where('quantity', '>', 0)
            ->selectRaw('SUM(quantity * price) / NULLIF(SUM(quantity), 0) as v')
            ->value('v');

        return $v !== null ? (float) $v : 0.0;
    }

    public static function syncUnitPriceFromWeightedAverage(int $categoryId): void
    {
        $row = DB::table('categories')->where('id', $categoryId)->first();
        if (! $row) {
            return;
        }
        $qty = (float) ($row->quantity ?? 0);
        if ($qty > 0) {
            $tp = (float) ($row->total_price ?? 0);
            DB::table('categories')->where('id', $categoryId)->update([
                'unit_price' => $tp / $qty,
            ]);
        }
    }
}
