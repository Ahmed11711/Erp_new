<?php

namespace App\Services\Inventory;

use App\Models\Stock;

/**
 * Keyword-based warehouse classification for new products.
 * Assigns items to Raw Materials, WIP, or Finished Goods warehouse.
 */
class WarehouseClassifier
{
    private const RAW_KEYWORDS = [
        'خام', 'مواد', 'كرتون', 'زجاجة', 'قارورة', 'قنينة', 'عبوة',
        'بلاستيك', 'نايلون', 'كيس', 'شريط', 'لفة', 'رول', 'لصق',
        'سكر', 'زيت', 'ملح', 'دقيق', 'بودر', 'مسحوق', 'صبغة', 'لون',
        'ماده', 'ماده خام', 'قماش', 'خيط', 'حبر', 'ورق', 'صاج',
        'حديد', 'المنيوم', 'نحاس', 'اسمنت', 'رمل', 'خشب',
        'بولي', 'فوم', 'سلوفان', 'استيكر', 'ليبل', 'غراء',
        'مادة', 'خامة', 'خامات',
        'raw', 'material', 'ingredient', 'chemical', 'fabric',
        'sugar', 'oil', 'powder', 'carton', 'bottle', 'paper',
    ];

    private const WIP_KEYWORDS = [
        'تحت التشغيل', 'تحت الانتاج', 'شبه', 'نصف', 'مخلوط', 'خلطة',
        'تجهيز', 'تحضير', 'مرحلة', 'وسيط', 'شبه مصنع',
        'semi', 'wip', 'processing', 'preparation', 'mix', 'blend',
        'work in progress', 'semi-finished', 'intermediate',
    ];

    private const FINISHED_KEYWORDS = [
        'منتج تام', 'منتج نهائي', 'جاهز', 'معبأ', 'مغلف',
        'تام', 'نهائي', 'جاهز للبيع', 'sku',
        'finished', 'final', 'ready', 'packed', 'packaged',
        'product', 'goods', 'complete',
    ];

    /**
     * @return array{warehouse_name: string, stock_id: int|null, confidence: string, method: string}
     */
    public function classify(string $productName, ?string $excelWarehouseName = null): array
    {
        if ($excelWarehouseName !== null && $excelWarehouseName !== '') {
            $stock = Stock::query()->where('name', $excelWarehouseName)->first();
            if ($stock) {
                return [
                    'warehouse_name' => $stock->name,
                    'stock_id' => $stock->id,
                    'confidence' => 'explicit',
                    'method' => 'excel_column',
                ];
            }

            $normalized = ArabicFuzzyMatcher::normalize($excelWarehouseName);
            if (str_contains($normalized, 'خام') || str_contains($normalized, 'raw')) {
                return $this->resolveStock('مخزن مواد خام', 'excel_hint');
            }
            if (str_contains($normalized, 'تشغيل') || str_contains($normalized, 'wip')) {
                return $this->resolveStock('مخزن منتج تحت التشغيل', 'excel_hint');
            }
            if (str_contains($normalized, 'تام') || str_contains($normalized, 'finished')) {
                return $this->resolveStock('مخزن منتج تام', 'excel_hint');
            }
        }

        $normalized = ArabicFuzzyMatcher::normalize($productName);

        $wipScore = $this->keywordScore($normalized, self::WIP_KEYWORDS);
        $finishedScore = $this->keywordScore($normalized, self::FINISHED_KEYWORDS);
        $rawScore = $this->keywordScore($normalized, self::RAW_KEYWORDS);

        if ($finishedScore > 0 && $finishedScore >= $wipScore && $finishedScore >= $rawScore) {
            return $this->resolveStock('مخزن منتج تام', 'keyword_high');
        }

        if ($wipScore > 0 && $wipScore >= $rawScore) {
            return $this->resolveStock('مخزن منتج تحت التشغيل', 'keyword_high');
        }

        if ($rawScore > 0) {
            return $this->resolveStock('مخزن مواد خام', 'keyword_high');
        }

        return $this->resolveStock('مخزن مواد خام', 'default_fallback');
    }

    private function keywordScore(string $text, array $keywords): int
    {
        $score = 0;
        foreach ($keywords as $kw) {
            if (mb_stripos($text, $kw) !== false) {
                $score += mb_strlen($kw);
            }
        }
        return $score;
    }

    /**
     * @return array{warehouse_name: string, stock_id: int|null, confidence: string, method: string}
     */
    private function resolveStock(string $warehouseName, string $method): array
    {
        $stock = Stock::query()->where('name', $warehouseName)->first();
        return [
            'warehouse_name' => $warehouseName,
            'stock_id' => $stock?->id,
            'confidence' => $method === 'default_fallback' ? 'low' : 'high',
            'method' => $method,
        ];
    }
}
