<?php

namespace App\Services\Shopify;

use App\Models\Category;
use App\Models\ShopifyProduct;
use App\Models\ShopifyProductMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopifyProductMappingSyncService
{
    private const EARLIEST_SYNC_CACHE_PREFIX = 'shopify:product_mappings_sync_earliest_min:';

    /** أقصى عدد صفوف تُعاد في JSON (المجموع الكامل في synced_variants). */
    private const SYNC_DETAIL_ROW_LIMIT = 400;

    /** Cached category lookup map (normalized name → category). */
    private ?array $categoryNameMap = null;

    /**
     * يجلب منتجات Shopify (مع المتغيرات) ويحدّث جدول shopify_product_mappings
     * وجدول shopify_products (لعرض لوحة Shopify ↔ الشحن والتحديث إلى المتجر).
     * التصنيف category_id للمتغيرات الجديدة يُبحث عنه بالاسم أولاً؛ إذا لم يوجد يُستخدم SHOPIFY_FALLBACK_CATEGORY_ID.
     *
     * @param  ?Carbon  $createdFrom  بداية نطاق created_at للمنتج في Shopify
     * @param  ?Carbon  $createdTo  نهاية النطاق (ضمن اليوم)
     * @param  ?int  $daysBack  عند غياب التاريخين: أقدم created_at = الآن ناقص N يوماً (لا يُستخدم مع من/إلى)
     */
    public function sync(?Carbon $createdFrom = null, ?Carbon $createdTo = null, ?int $daysBack = null): array
    {
        $client = ShopifyAdminApiClient::fromConfig();

        $fallbackCategoryId = (int) config('services.shopify.fallback_category_id');
        if ($fallbackCategoryId < 1) {
            throw new \RuntimeException(
                'Shopify: عيّن SHOPIFY_FALLBACK_CATEGORY_ID (تصنيف افتراضي لربط أصناف المتجر).'
            );
        }

        if (! Category::query()->whereKey($fallbackCategoryId)->exists()) {
            throw new \RuntimeException(
                "Shopify: SHOPIFY_FALLBACK_CATEGORY_ID={$fallbackCategoryId} غير موجود في جدول categories. عيّن معرف تصنيف صنف (category) فعلي من شاشة الأصناف."
            );
        }

        $shopDomain = $client->normalizedShopHost();

        $query = ['limit' => 250];
        $effectiveRangeStart = null;
        $createdAtMinIso = null;
        $createdAtMaxIso = null;
        $daysForReport = null;

        if ($createdFrom !== null && $createdTo !== null) {
            if ($createdFrom->greaterThan($createdTo)) {
                throw new \InvalidArgumentException('تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.');
            }
            $start = $createdFrom->copy()->startOfDay();
            $end = $createdTo->copy()->endOfDay();
            $query['created_at_min'] = $start->toIso8601String();
            $query['created_at_max'] = $end->toIso8601String();
            $effectiveRangeStart = $start;
            $createdAtMinIso = $query['created_at_min'];
            $createdAtMaxIso = $query['created_at_max'];
        } elseif ($daysBack !== null && $daysBack > 0) {
            $effectiveRangeStart = Carbon::now()->subDays($daysBack);
            $query['created_at_min'] = $effectiveRangeStart->toIso8601String();
            $createdAtMinIso = $query['created_at_min'];
            $daysForReport = $daysBack;
        }

        [$syncOverlapWarning, $syncOverlapMessage] = $this->detectOlderRangeWarning($shopDomain, $effectiveRangeStart);

        $nextUrl = null;
        $pages = 0;
        $synced = 0;
        $variantsCreated = 0;
        $variantsUpdated = 0;
        $variantsAutoMatched = 0;
        $detailRows = [];
        $unmatchedProducts = [];
        $priceDifferences = [];

        do {
            $response = $nextUrl
                ? $client->getAbsolute($nextUrl)
                : $client->get('products.json', $query);

            if (! $response->successful()) {
                Log::error('Shopify products API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException(
                    ShopifyAdminApiClient::formatAdminApiFailureMessage($response, 'فشل طلب منتجات Shopify')
                );
            }

            $pages++;
            $body = $response->json();
            $products = is_array($body) && isset($body['products']) && is_array($body['products'])
                ? $body['products']
                : [];

            foreach ($products as $product) {
                if (! is_array($product)) {
                    continue;
                }
                $productId = isset($product['id']) ? (int) $product['id'] : null;
                $productTitle = isset($product['title']) ? trim((string) $product['title']) : '';
                foreach ($product['variants'] ?? [] as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    $variantId = isset($variant['id']) ? (int) $variant['id'] : null;
                    if (! $variantId) {
                        continue;
                    }
                    $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';
                    $variantTitleRaw = isset($variant['title']) ? trim((string) $variant['title']) : '';
                    $variantLabel = $variantTitleRaw !== '' && strtolower($variantTitleRaw) !== 'default title'
                        ? $variantTitleRaw
                        : '';

                    $existing = ShopifyProductMapping::query()
                        ->where('shopify_variant_id', $variantId)
                        ->first();

                    $wasExisting = $existing !== null;
                    $autoMatched = false;
                    $priceRaw = $variant['price'] ?? null;
                    $shopifyPrice = is_numeric($priceRaw) ? (float) $priceRaw : 0.0;

                    if ($existing) {
                        $categoryId = $this->resolveCategoryIdForMapping((int) $existing->category_id, $fallbackCategoryId);
                    } else {
                        $matchedCategory = $this->matchCategoryByName($productTitle, $variantLabel);
                        if ($matchedCategory) {
                            $categoryId = (int) $matchedCategory->id;
                            $autoMatched = true;
                            $variantsAutoMatched++;
                        } else {
                            $categoryId = $fallbackCategoryId;
                            $shopifyDisplayName = $this->buildVariantDisplayName($productTitle, $variantLabel, $variantId);
                            $normalizedName = $this->normalizeShopifyNameToErp($productTitle, $variantLabel);
                            $unmatchedProducts[] = [
                                'shopify_product_id' => $productId,
                                'shopify_variant_id' => $variantId,
                                'shopify_name' => $shopifyDisplayName,
                                'expected_erp_name' => $normalizedName,
                                'sku' => $sku !== '' ? $sku : null,
                                'price' => $shopifyPrice,
                            ];
                        }
                    }

                    if ($categoryId !== $fallbackCategoryId) {
                        $erpCategory = Category::query()->find($categoryId);
                        if ($erpCategory) {
                            $erpPrice = (float) ($erpCategory->sell_total_price ?? $erpCategory->category_price ?? 0);
                            if ($erpPrice > 0 && $shopifyPrice > 0 && abs($erpPrice - $shopifyPrice) > 0.01) {
                                $priceDifferences[] = [
                                    'shopify_variant_id' => $variantId,
                                    'shopify_name' => $this->buildVariantDisplayName($productTitle, $variantLabel, $variantId),
                                    'category_id' => $categoryId,
                                    'category_name' => $erpCategory->category_name,
                                    'shopify_price' => $shopifyPrice,
                                    'erp_price' => $erpPrice,
                                ];
                            }
                        }
                    }

                    ShopifyProductMapping::query()->updateOrCreate(
                        ['shopify_variant_id' => $variantId],
                        [
                            'shop_domain' => $shopDomain,
                            'shopify_product_id' => $productId,
                            'sku' => $sku !== '' ? $sku : null,
                            'category_id' => $categoryId,
                            'active' => true,
                        ]
                    );

                    if ($productId !== null) {
                        $displayName = $this->buildVariantDisplayName($productTitle, $variantLabel, $variantId);
                        $price = is_numeric($priceRaw) ? (string) $priceRaw : (is_string($priceRaw) && $priceRaw !== '' ? $priceRaw : '0');
                        $inventoryItemId = isset($variant['inventory_item_id']) ? (int) $variant['inventory_item_id'] : null;

                        $productRow = [
                            'shop_domain' => $shopDomain,
                            'name' => $displayName,
                            'sku' => $sku !== '' ? $sku : null,
                            'price' => $price,
                            'category_id' => $categoryId,
                            'inventory_item_id' => $inventoryItemId ?: null,
                        ];
                        if (array_key_exists('inventory_quantity', $variant)) {
                            $productRow['quantity'] = max(0, (int) $variant['inventory_quantity']);
                        }

                        ShopifyProduct::query()->updateOrCreate(
                            [
                                'shopify_product_id' => $productId,
                                'shopify_variant_id' => $variantId,
                            ],
                            $productRow
                        );
                    }

                    $synced++;
                    if ($wasExisting) {
                        $variantsUpdated++;
                    } else {
                        $variantsCreated++;
                    }
                    if (count($detailRows) < self::SYNC_DETAIL_ROW_LIMIT) {
                        $detailRows[] = [
                            'action' => $wasExisting ? 'updated' : ($autoMatched ? 'auto_matched' : 'created'),
                            'shopify_product_id' => $productId,
                            'shopify_variant_id' => $variantId,
                            'product_title' => $productTitle,
                            'variant_label' => $variantLabel,
                            'sku' => $sku !== '' ? $sku : null,
                            'category_id' => $categoryId,
                            'auto_matched' => $autoMatched,
                        ];
                    }
                }
            }

            $nextUrl = ShopifyAdminApiClient::extractNextPageUrl($response->header('Link'));
        } while ($nextUrl !== null);

        if ($effectiveRangeStart !== null) {
            $this->rememberEarliestSyncedMin($shopDomain, $effectiveRangeStart);
        }

        Log::info('Shopify product mappings synced', [
            'shop' => $shopDomain,
            'synced_variants' => $synced,
            'variants_created' => $variantsCreated,
            'variants_updated' => $variantsUpdated,
            'variants_auto_matched' => $variantsAutoMatched,
            'unmatched_count' => count($unmatchedProducts),
            'price_differences_count' => count($priceDifferences),
            'pages' => $pages,
            'created_at_min' => $createdAtMinIso,
            'created_at_max' => $createdAtMaxIso,
            'days_window' => $daysForReport,
            'sync_overlap_warning' => $syncOverlapWarning,
        ]);

        return [
            'synced_variants' => $synced,
            'pages_fetched' => $pages,
            'shop_domain' => $shopDomain,
            'created_at_min' => $createdAtMinIso,
            'created_at_max' => $createdAtMaxIso,
            'days_window' => $daysForReport,
            'sync_overlap_warning' => $syncOverlapWarning,
            'sync_overlap_message' => $syncOverlapMessage,
            'variants_created' => $variantsCreated,
            'variants_updated' => $variantsUpdated,
            'variants_auto_matched' => $variantsAutoMatched,
            'sync_detail_rows' => $detailRows,
            'sync_detail_truncated' => $synced > count($detailRows),
            'unmatched_products' => $unmatchedProducts,
            'unmatched_count' => count($unmatchedProducts),
            'price_differences' => array_slice($priceDifferences, 0, 100),
            'price_differences_count' => count($priceDifferences),
        ];
    }

    /**
     * Normalizes a Shopify product+variant title to the ERP naming convention.
     *
     * Rules:
     * - Replace em-dash (—) with hyphen (-)
     * - Yes/No (متغير أو لاحقة): Yes → يلحق « - Footrest»، No → بدون footrest
     *   الصيغ: «/ Yes»، «- Yes»، أو المتغير وحده «Yes» (ونظائر No)
     * - Collapse multiple spaces/dashes into clean format
     */
    public function normalizeShopifyNameToErp(string $productTitle, string $variantLabel): string
    {
        $productTitle = str_replace(['—', '–'], '-', trim($productTitle));
        $variantLabel = str_replace(['—', '–'], '-', trim($variantLabel));

        $fromProduct = $this->parseFootrestFromSegment($productTitle);
        $productTitle = $fromProduct['base'];
        $productFootrest = $fromProduct['has_footrest'];

        $fromVariant = $this->parseFootrestFromSegment($variantLabel);
        $cleanedVariant = $fromVariant['base'];
        $variantFootrest = $fromVariant['has_footrest'];

        $hasFootrest = $variantFootrest ?? $productFootrest ?? false;

        $productTitle = $this->slashesToDashSegments($productTitle);
        $productTitle = trim(preg_replace('/\s*-\s*/', ' - ', $productTitle));

        $cleanedVariant = $this->slashesToDashSegments($cleanedVariant);
        $cleanedVariant = trim(preg_replace('/\s*-\s*/', ' - ', $cleanedVariant));

        $parts = [];
        if ($productTitle !== '') {
            $parts[] = $productTitle;
        }

        if ($cleanedVariant !== '' && strtolower($cleanedVariant) !== 'default title') {
            if ($productTitle === '' || ! $this->segmentAlreadyInTitle($productTitle, $cleanedVariant)) {
                $parts[] = $cleanedVariant;
            }
        }

        if ($hasFootrest) {
            $parts[] = 'Footrest';
        }

        if ($parts === []) {
            return '';
        }

        return $this->cleanNormalizedName(implode(' - ', $parts));
    }

    /**
     * يستخرج علامة footrest من جزء الاسم (عنوان منتج أو متغير Shopify).
     *
     * @return array{base: string, has_footrest: ?bool} has_footrest: true=Yes، false=No، null=بدون علامة
     */
    private function parseFootrestFromSegment(string $segment): array
    {
        $segment = trim($segment);
        if ($segment === '') {
            return ['base' => '', 'has_footrest' => null];
        }

        if (preg_match('#^(?:Yes|No)$#iu', $segment, $m)) {
            return [
                'base' => '',
                'has_footrest' => strtolower($m[0]) === 'yes',
            ];
        }

        if (preg_match('#^(.+?)\s*/\s*(Yes|No)\s*$#iu', $segment, $m)) {
            return [
                'base' => trim($m[1]),
                'has_footrest' => strtolower($m[2]) === 'yes',
            ];
        }

        if (preg_match('#^(.+?)\s*-\s*(Yes|No)\s*$#iu', $segment, $m)) {
            return [
                'base' => trim($m[1]),
                'has_footrest' => strtolower($m[2]) === 'yes',
            ];
        }

        return ['base' => $segment, 'has_footrest' => null];
    }

    /**
     * يحوّل «Leather/Brown» إلى «Leather - Brown» بصيغة ERP.
     */
    private function slashesToDashSegments(string $name): string
    {
        return trim(preg_replace('#\s*/\s*#', ' - ', $name));
    }

    /**
     * يزيل علامة Yes/No من نهاية جزء الاسم (للاستخدام الداخلي إن لزم).
     */
    private function stripFootrestSuffixFromSegment(string $segment): string
    {
        return $this->parseFootrestFromSegment($segment)['base'];
    }

    /**
     * true إذا كان المتغير (بعد التطبيع) مدمجاً في عنوان المنتج — مثل Alma Chair - Leather - Brown.
     */
    private function segmentAlreadyInTitle(string $productTitle, string $variantSegment): bool
    {
        $titleKey = $this->normalizeCategoryNameForComparison($productTitle);
        $variantKey = $this->normalizeCategoryNameForComparison($variantSegment);

        if ($variantKey === '') {
            return true;
        }

        return str_ends_with($titleKey, $variantKey) || str_contains($titleKey, $variantKey);
    }

    private function cleanNormalizedName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\s*-\s*-\s*/', ' - ', $name);

        return trim($name, ' -');
    }

    /**
     * Attempts to find a matching ERP category by normalized product name.
     */
    private function matchCategoryByName(string $productTitle, string $variantLabel): ?Category
    {
        $normalizedShopifyName = $this->normalizeShopifyNameToErp($productTitle, $variantLabel);
        if ($normalizedShopifyName === '') {
            return null;
        }

        $map = $this->getCategoryNameMap();

        $key = $this->normalizeCategoryNameForComparison($normalizedShopifyName);
        if (isset($map[$key])) {
            return $map[$key];
        }

        return null;
    }

    /**
     * Builds/caches a normalized name → Category lookup map for all ERP categories.
     */
    private function getCategoryNameMap(): array
    {
        if ($this->categoryNameMap !== null) {
            return $this->categoryNameMap;
        }

        $this->categoryNameMap = [];
        $categories = Category::query()->whereNotNull('category_name')->get();

        foreach ($categories as $cat) {
            $key = $this->normalizeCategoryNameForComparison($cat->category_name);
            if ($key !== '') {
                $this->categoryNameMap[$key] = $cat;
            }
        }

        return $this->categoryNameMap;
    }

    /**
     * مفتاح مقارنة موحّد لاسم صنف (للاستخدام من خدمات أخرى).
     */
    public function comparisonKeyForName(string $name): string
    {
        return $this->normalizeCategoryNameForComparison($name);
    }

    /**
     * يبحث عن صنف ERP موجود يطابق اسماً (بعد التطبيع) لتفادي إنشاء صنف مكرر.
     * يتعامل مع اختلاف الفواصل مثل «X and footrest» مقابل «X - Footrest».
     */
    public function findCategoryByNormalizedName(string $erpName): ?Category
    {
        $erpName = trim($erpName);
        if ($erpName === '') {
            return null;
        }

        $map = $this->getCategoryNameMap();
        $key = $this->normalizeCategoryNameForComparison($erpName);

        return $key !== '' ? ($map[$key] ?? null) : null;
    }

    /**
     * Normalizes a name for case-insensitive, whitespace-insensitive comparison.
     *
     * يعامل الكلمة الرابطة «and» (وما يماثلها) كفاصل «-» حتى تتطابق الأسماء
     * المُدخلة يدوياً مثل «Forest and footrest» مع صيغة Shopify «Forest - Footrest».
     * يُزال الشرطة نفسياً مع المسافات حتى تتطابق «Ovia Set - Flamingo Pink»
     * مع «Ovia Set flamingo pink» دون إنشاء صنف مكرر.
     */
    private function normalizeCategoryNameForComparison(string $name): string
    {
        $name = mb_strtolower($name);
        $name = str_replace(['—', '–'], '-', $name);
        $name = preg_replace('/\s+and\s+/u', '-', $name);
        // Shopify Yes/No ↔ ERP footrest عند المقارنة
        $name = preg_replace('/-yes$/', '-footrest', $name);
        $name = preg_replace('/-no$/', '', $name);
        // Shopify: Leather/Brown — ERP: Leather - Brown
        $name = preg_replace('#\s*/\s*#', '-', $name);
        $name = preg_replace('/\s*-\s*/', '-', $name);
        $name = preg_replace('/\s+/', '', $name);
        $name = str_replace('-', '', $name);

        return $name;
    }

    private function detectOlderRangeWarning(string $shopDomain, ?Carbon $effectiveRangeStart): array
    {
        if ($effectiveRangeStart === null) {
            return [false, null];
        }

        $cacheKey = self::EARLIEST_SYNC_CACHE_PREFIX.$shopDomain;
        $prev = Cache::get($cacheKey);

        if (! is_array($prev) || empty($prev['earliest_min'])) {
            return [false, null];
        }

        try {
            $prevEarliest = Carbon::parse($prev['earliest_min']);
        } catch (\Throwable) {
            return [false, null];
        }

        if ($effectiveRangeStart->lt($prevEarliest)) {
            return [
                true,
                'تنبيه: بداية نطاق تاريخ المنتجات أقدم من أقدم نطاق سبق مزامنته لهذا المتجر. سيتم تحديث سجلات الربط الموجودة دون تكرار الصفوف.',
            ];
        }

        return [false, null];
    }

    /**
     * يحافظ على category_id المحفوظ إن كان التصنيف ما زال موجوداً؛ وإلا يستخدم الافتراضي (لتفادي كسر FK).
     */
    private function resolveCategoryIdForMapping(int $storedCategoryId, int $fallbackCategoryId): int
    {
        if ($storedCategoryId >= 1 && Category::query()->whereKey($storedCategoryId)->exists()) {
            return $storedCategoryId;
        }

        return $fallbackCategoryId;
    }

    private function buildVariantDisplayName(string $productTitle, string $variantLabel, int $variantId): string
    {
        if ($productTitle !== '' && $variantLabel !== '') {
            return $productTitle.' — '.$variantLabel;
        }
        if ($productTitle !== '') {
            return $productTitle;
        }
        if ($variantLabel !== '') {
            return $variantLabel;
        }

        return 'متغير #'.$variantId;
    }

    private function rememberEarliestSyncedMin(string $shopDomain, Carbon $rangeStart): void
    {
        $cacheKey = self::EARLIEST_SYNC_CACHE_PREFIX.$shopDomain;
        $prev = Cache::get($cacheKey);
        $prevEarliest = null;

        if (is_array($prev) && ! empty($prev['earliest_min'])) {
            try {
                $prevEarliest = Carbon::parse($prev['earliest_min']);
            } catch (\Throwable) {
                $prevEarliest = null;
            }
        }

        $newEarliest = $prevEarliest === null || $rangeStart->lt($prevEarliest)
            ? $rangeStart->copy()
            : $prevEarliest;

        Cache::forever($cacheKey, [
            'earliest_min' => $newEarliest->toIso8601String(),
        ]);
    }
}
