<?php

namespace App\Services\Offers;

use App\Models\Category;
use App\Models\Measurement;
use App\Models\Offers;
use App\Models\OffersCategory;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Stock;
use App\Support\ArabicTextNormalizer;

class OfferProductMatchService
{
    public const WAREHOUSE_FINISHED = 'مخزن منتج تام';

    /**
     * تحليل بنود عرض السعر مقابل الأصناف ووصفات التصنيع.
     *
     * @return array{lines: list<array<string,mixed>>, summary: array<string,int>}
     */
    public function analyze(Offers $offer): array
    {
        $offer->loadMissing('category');
        $catalog = $this->buildCatalogIndex();
        $manualHits = $this->resolveManualMatches($offer);

        $lines = [];
        $summary = [
            'total' => 0,
            'matched' => 0,
            'matched_space_diff' => 0,
            'matched_manual' => 0,
            'missing_category' => 0,
            'missing_recipe' => 0,
        ];

        foreach ($offer->category as $line) {
            $name = trim((string) ($line->category_name ?? ''));
            if ($name === '') {
                continue;
            }

            $summary['total']++;
            $lineId = (int) $line->id;

            $manualHit = $manualHits[$lineId] ?? null;
            if ($manualHit) {
                $row = $this->buildMatchedLine($lineId, $name, $manualHit, 'manual');
                $summary['matched_manual']++;
                $summary['matched']++;
                if ($row['status'] === 'missing_recipe') {
                    $summary['missing_recipe']++;
                }
                $lines[] = $row;
                continue;
            }

            $normalized = ArabicTextNormalizer::normalize($name);
            $compact = ArabicTextNormalizer::compact($name);

            $hit = $catalog['by_normalized'][$normalized]
                ?? $catalog['by_compact'][$compact]
                ?? null;

            if (! $hit) {
                $summary['missing_category']++;
                $lines[] = [
                    'offer_line_id' => $lineId,
                    'offer_name' => $name,
                    'status' => 'missing_category',
                    'match_type' => null,
                    'category' => null,
                    'has_recipe' => false,
                    'recipe_id' => null,
                    'suggestion' => 'اختر صنفاً موجوداً باسم مختلف، أو أنشئ صنفاً جديداً بهذا الاسم',
                    'actions' => ['link_category', 'create_category'],
                ];
                continue;
            }

            $matchType = ($catalog['by_normalized'][$normalized] ?? null)
                ? 'exact_normalized'
                : 'space_insensitive';

            if ($matchType === 'space_insensitive') {
                $summary['matched_space_diff']++;
            } else {
                $summary['matched']++;
            }

            $row = $this->buildMatchedLine($lineId, $name, $hit, $matchType);
            if ($row['status'] === 'missing_recipe') {
                $summary['missing_recipe']++;
            }
            $lines[] = $row;
        }

        return [
            'lines' => $lines,
            'summary' => $summary,
        ];
    }

    /**
     * ربط بند عرض يدوياً بصنف موجود (عند اختلاف الاسم).
     *
     * @return array{line: OffersCategory, analysis: array}
     */
    public function linkMatchedCategory(Offers $offer, int $offerLineId, int $categoryId): array
    {
        $line = OffersCategory::query()
            ->where('offer_id', $offer->id)
            ->whereKey($offerLineId)
            ->first();

        if (! $line) {
            throw new \InvalidArgumentException('بند العرض غير موجود.');
        }

        $category = Category::query()
            ->select(['id', 'category_name', 'warehouse', 'stock_id', 'recipe_id', 'category_price'])
            ->whereKey($categoryId)
            ->first();

        if (! $category) {
            throw new \InvalidArgumentException('الصنف المختار غير موجود.');
        }

        $line->matched_category_id = (int) $category->id;
        $line->save();

        return [
            'line' => $line->fresh(),
            'analysis' => $this->analyze($offer->fresh(['category'])),
        ];
    }

    /**
     * إلغاء الربط اليدوي لبند العرض.
     *
     * @return array{line: OffersCategory, analysis: array}
     */
    public function clearMatchedCategory(Offers $offer, int $offerLineId): array
    {
        $line = OffersCategory::query()
            ->where('offer_id', $offer->id)
            ->whereKey($offerLineId)
            ->first();

        if (! $line) {
            throw new \InvalidArgumentException('بند العرض غير موجود.');
        }

        $line->matched_category_id = null;
        $line->save();

        return [
            'line' => $line->fresh(),
            'analysis' => $this->analyze($offer->fresh(['category'])),
        ];
    }

    /**
     * @param  array<string,mixed>  $hit
     * @return array<string,mixed>
     */
    private function buildMatchedLine(int $lineId, string $name, array $hit, string $matchType): array
    {
        $hasRecipe = ! empty($hit['recipe_id']) || ! empty($hit['is_recipe_output']);
        $recipeId = $hit['recipe_id'] ?: ($hit['output_recipe_id'] ?? null);

        $suggestion = match ($matchType) {
            'manual' => $hasRecipe
                ? 'مربوط يدوياً بصنف موجود وله وصفة'
                : 'مربوط يدوياً بصنف موجود لكن بدون وصفة تصنيع — يُقترح إنشاء وصفة',
            'space_insensitive' => $hasRecipe
                ? 'موجود كصنف (اختلاف مسافات في الاسم) وله وصفة'
                : 'موجود كصنف (اختلاف مسافات) لكن بدون وصفة تصنيع — يُقترح إنشاء وصفة',
            default => $hasRecipe
                ? 'موجود وله وصفة تصنيع'
                : 'موجود كصنف لكن بدون وصفة تصنيع — يُقترح إنشاء وصفة',
        };

        return [
            'offer_line_id' => $lineId,
            'offer_name' => $name,
            'status' => $hasRecipe ? 'ok' : 'missing_recipe',
            'match_type' => $matchType,
            'category' => [
                'id' => $hit['id'],
                'category_name' => $hit['category_name'],
                'warehouse' => $hit['warehouse'],
                'category_price' => $hit['category_price'],
            ],
            'has_recipe' => $hasRecipe,
            'recipe_id' => $recipeId ? (int) $recipeId : null,
            'suggestion' => $suggestion,
            'actions' => array_values(array_filter([
                'link_category',
                $hasRecipe ? null : 'create_recipe',
            ])),
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function resolveManualMatches(Offers $offer): array
    {
        $ids = [];
        foreach ($offer->category as $line) {
            $catId = (int) ($line->matched_category_id ?? 0);
            if ($catId > 0) {
                $ids[$catId] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $recipeOutputs = Recipe::query()
            ->whereNotNull('output_item_id')
            ->whereIn('output_item_id', array_keys($ids))
            ->pluck('id', 'output_item_id');

        $categories = Category::query()
            ->select(['id', 'category_name', 'warehouse', 'stock_id', 'recipe_id', 'category_price'])
            ->whereIn('id', array_keys($ids))
            ->get()
            ->keyBy('id');

        $manual = [];
        foreach ($offer->category as $line) {
            $catId = (int) ($line->matched_category_id ?? 0);
            if ($catId <= 0 || ! $categories->has($catId)) {
                continue;
            }
            $row = $categories->get($catId);
            $manual[(int) $line->id] = [
                'id' => (int) $row->id,
                'category_name' => (string) $row->category_name,
                'warehouse' => $row->warehouse,
                'category_price' => (float) ($row->category_price ?? 0),
                'recipe_id' => $row->recipe_id ? (int) $row->recipe_id : null,
                'is_recipe_output' => $recipeOutputs->has($row->id),
                'output_recipe_id' => $recipeOutputs->get($row->id),
            ];
        }

        return $manual;
    }

    /**
     * إنشاء أصناف ناقصة من بنود العرض (مع منع التكرار بالاسم المضغوط).
     *
     * @param  list<int>|null  $offerLineIds
     * @return array{created: list<array>, skipped: list<array>}
     */
    public function createMissingCategories(Offers $offer, ?array $offerLineIds = null): array
    {
        $analysis = $this->analyze($offer);
        $defaults = $this->resolveCreateDefaults();

        $created = [];
        $skipped = [];

        foreach ($analysis['lines'] as $row) {
            if ($row['status'] !== 'missing_category') {
                continue;
            }
            if ($offerLineIds !== null && ! in_array((int) $row['offer_line_id'], $offerLineIds, true)) {
                continue;
            }

            $name = trim((string) $row['offer_name']);
            $compact = ArabicTextNormalizer::compact($name);

            // إعادة فحص لتجنب السباق / التكرار
            $existing = $this->findCategoryByCompactOrNormalized($name);
            if ($existing) {
                $skipped[] = [
                    'offer_line_id' => $row['offer_line_id'],
                    'offer_name' => $name,
                    'reason' => 'موجود مسبقاً: ' . $existing->category_name,
                    'category_id' => (int) $existing->id,
                ];
                continue;
            }

            $line = $offer->category->firstWhere('id', $row['offer_line_id']);
            $price = (float) ($line->new_category_price ?? $line->old_category_price ?? 0);

            $category = Category::create([
                'category_name' => $name,
                'category_price' => $price,
                'unit_price' => $price,
                'initial_balance' => 0,
                'minimum_quantity' => 0,
                'warehouse' => self::WAREHOUSE_FINISHED,
                'production_id' => $defaults['production_id'],
                'measurement_id' => $defaults['measurement_id'],
                'stock_id' => $defaults['stock_id'],
                'product_type' => 'finished',
                'quantity' => 0,
            ]);

            $created[] = [
                'offer_line_id' => $row['offer_line_id'],
                'offer_name' => $name,
                'category_id' => (int) $category->id,
                'category_name' => $category->category_name,
            ];
        }

        return compact('created', 'skipped');
    }

    public function findCategoryByCompactOrNormalized(string $name): ?Category
    {
        $normalized = ArabicTextNormalizer::normalize($name);
        $compact = ArabicTextNormalizer::compact($name);
        if ($normalized === '' && $compact === '') {
            return null;
        }

        $found = null;
        Category::query()
            ->select(['id', 'category_name', 'warehouse', 'stock_id', 'recipe_id', 'category_price'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$found, $normalized, $compact) {
                foreach ($rows as $row) {
                    $n = ArabicTextNormalizer::normalize((string) $row->category_name);
                    $c = ArabicTextNormalizer::compact((string) $row->category_name);
                    if (($normalized !== '' && $n === $normalized) || ($compact !== '' && $c === $compact)) {
                        $found = $row;
                        return false;
                    }
                }
            });

        return $found;
    }

    /**
     * @return array{by_normalized: array<string,array>, by_compact: array<string,array>}
     */
    private function buildCatalogIndex(): array
    {
        $byNormalized = [];
        $byCompact = [];

        $recipeOutputs = Recipe::query()
            ->whereNotNull('output_item_id')
            ->pluck('id', 'output_item_id');

        Category::query()
            ->select(['id', 'category_name', 'warehouse', 'stock_id', 'recipe_id', 'category_price'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$byNormalized, &$byCompact, $recipeOutputs) {
                foreach ($rows as $row) {
                    $name = (string) $row->category_name;
                    $normalized = ArabicTextNormalizer::normalize($name);
                    $compact = ArabicTextNormalizer::compact($name);
                    if ($normalized === '') {
                        continue;
                    }

                    $payload = [
                        'id' => (int) $row->id,
                        'category_name' => $name,
                        'warehouse' => $row->warehouse,
                        'category_price' => (float) ($row->category_price ?? 0),
                        'recipe_id' => $row->recipe_id ? (int) $row->recipe_id : null,
                        'is_recipe_output' => $recipeOutputs->has($row->id),
                        'output_recipe_id' => $recipeOutputs->get($row->id),
                    ];

                    // فضّل صنف مخزن المنتج التام عند التعارض
                    $prefer = ($row->warehouse === self::WAREHOUSE_FINISHED);

                    if (! isset($byNormalized[$normalized]) || $prefer) {
                        $byNormalized[$normalized] = $payload;
                    }
                    if ($compact !== '' && (! isset($byCompact[$compact]) || $prefer)) {
                        $byCompact[$compact] = $payload;
                    }
                }
            });

        return [
            'by_normalized' => $byNormalized,
            'by_compact' => $byCompact,
        ];
    }

    /**
     * @return array{production_id:int, measurement_id:int, stock_id:int}
     */
    private function resolveCreateDefaults(): array
    {
        $productionId = (int) (Production::query()->where('warehouse', self::WAREHOUSE_FINISHED)->orderBy('id')->value('id')
            ?: Production::query()->orderBy('id')->value('id'));
        $measurementId = (int) (Measurement::query()->orderBy('id')->value('id'));

        if ($productionId <= 0 || $measurementId <= 0) {
            throw new \RuntimeException('تعذر تحديد خط الإنتاج أو وحدة القياس الافتراضية لإنشاء الصنف.');
        }

        $stock = Stock::query()->where('name', self::WAREHOUSE_FINISHED)->first();
        if (! $stock) {
            $assetId = Stock::query()->value('asset_id') ?: 1;
            $stock = Stock::create([
                'name' => self::WAREHOUSE_FINISHED,
                'balance' => 0,
                'asset_id' => (int) $assetId,
                'active' => true,
            ]);
        }

        return [
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
            'stock_id' => (int) $stock->id,
        ];
    }
}
