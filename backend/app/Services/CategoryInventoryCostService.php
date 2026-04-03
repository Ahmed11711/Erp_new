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
        // متوسط التكلفة المرجح من قيمة المخزون / الكمية (دائماً عند وجود كمية)
        if ($q > 0.0000001) {
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

    /**
     * تكلفة وحدة البند في فاتورة المشتريات (إجمالي السطر ÷ الكمية) لتتوافق طبقات warehouse_ratings مع قيمة المخزون.
     */
    public static function purchaseLineUnitCost(float $lineTotal, float $quantity, float $fallbackUnitPrice): float
    {
        if ($quantity > 0.0000001) {
            return $lineTotal / $quantity;
        }

        return $fallbackUnitPrice;
    }

    /**
     * صنف واحد لكل سطر فاتورة مشتريات (واجهة المشتريات تختار من مخزن مواد خام).
     * يفضّل category_id المُرسل من الواجهة؛ وإلا يُطابق الاسم مع مخزن مواد خام فقط (أول صف عند التكرار).
     * تحديث categories باسم الصنف فقط كان يُحدّث كل الصفوف ذات الاسم في كل المخازن فيفسد total_price ومتوسط التكلفة.
     *
     * @param  array|object  $product
     */
    public static function resolveCategoryIdForPurchaseLine($product, string $productName): ?int
    {
        $cid = null;
        if (is_array($product) && ! empty($product['category_id'])) {
            $cid = (int) $product['category_id'];
        } elseif (is_object($product) && isset($product->category_id) && (int) $product->category_id > 0) {
            $cid = (int) $product->category_id;
        }
        if ($cid && $cid > 0 && DB::table('categories')->where('id', $cid)->exists()) {
            return $cid;
        }

        $row = DB::table('categories')
            ->where('category_name', $productName)
            ->where('warehouse', 'مخزن مواد خام')
            ->orderBy('id')
            ->first();

        return $row ? (int) $row->id : null;
    }
}
