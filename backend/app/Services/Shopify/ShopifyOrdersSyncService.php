<?php

namespace App\Services\Shopify;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyOrdersSyncService
{
    private const EARLIEST_SYNC_CACHE_PREFIX = 'shopify:orders_sync_earliest_min:';

    private const PREVIEW_CACHE_PREFIX = 'shopify:order_sync_preview:';

    private const PREVIEW_TTL_SECONDS = 3600;

    public function __construct(
        private ShopifyOrderImportService $orderImportService,
        private ShopifyOrderUnmatchedProductService $unmatchedProductService,
    ) {}

    /**
     * معاينة قبل الاستيراد: Pull Orders، اكتشاف المنتجات غير المربوطة، وتخزين الحمولة مؤقتاً.
     *
     * @return array<string, mixed>
     */
    public function preview(int $daysWindow = 30, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $fetched = $this->fetchAllOrderPayloads($daysWindow, $dateFrom, $dateTo);
        $unmatched = $this->unmatchedProductService->mergeFromPayloads(
            $fetched['payloads'],
            $fetched['shop_domain']
        );

        $previewToken = (string) Str::uuid();
        Cache::put(self::PREVIEW_CACHE_PREFIX.$previewToken, [
            'payloads' => $fetched['payloads'],
            'shop_domain' => $fetched['shop_domain'],
            'meta' => $fetched['meta'],
        ], self::PREVIEW_TTL_SECONDS);

        return array_merge($fetched['meta'], [
            'preview_token' => $previewToken,
            'orders_fetched' => count($fetched['payloads']),
            'unmatched_products' => $unmatched,
            'unmatched_count' => count($unmatched),
            'needs_product_resolution' => count($unmatched) > 0,
            'shop_domain' => $fetched['shop_domain'],
        ]);
    }

    /**
     * استيراد الطلبات من جلسة معاينة سابقة (بدون إعادة جلب من Shopify).
     *
     * @return array<string, mixed>
     */
    public function importFromPreviewToken(string $previewToken): array
    {
        $cached = Cache::get(self::PREVIEW_CACHE_PREFIX.$previewToken);
        if (! is_array($cached) || empty($cached['payloads']) || ! is_array($cached['payloads'])) {
            throw new \RuntimeException('انتهت صلاحية المعاينة أو لم تُعثر على الطلبات. أعد «Pull Orders» من البداية.');
        }

        $result = $this->importPayloads(
            $cached['payloads'],
            (string) ($cached['shop_domain'] ?? ''),
            is_array($cached['meta'] ?? null) ? $cached['meta'] : []
        );

        Cache::forget(self::PREVIEW_CACHE_PREFIX.$previewToken);

        return array_merge($result, [
            'orders_fetched' => count($cached['payloads']),
        ]);
    }

    /**
     * Pull Orders Shopify واستيراد الجديد فقط؛ الطلبات الموجودة مسبقاً (shopify_order_id) تُتخطى دون تعديل.
     *
     * @return array<string, mixed>
     */
    public function sync(int $daysWindow = 30, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $fetched = $this->fetchAllOrderPayloads($daysWindow, $dateFrom, $dateTo);

        return $this->importPayloads(
            $fetched['payloads'],
            $fetched['shop_domain'],
            $fetched['meta']
        );
    }

    /**
     * @return array{payloads: array<int, array<string, mixed>>, shop_domain: string, meta: array<string, mixed>}
     */
    private function fetchAllOrderPayloads(int $daysWindow, ?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $client = ShopifyAdminApiClient::fromConfig();
        $shopDomain = $client->normalizedShopHost();

        $query = ['limit' => 250, 'status' => 'any'];
        $effectiveRangeStart = null;
        $createdAtMinIso = null;
        $createdAtMaxIso = null;

        if ($dateFrom !== null && $dateTo !== null) {
            if ($dateFrom->greaterThan($dateTo)) {
                throw new \InvalidArgumentException('تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.');
            }
            $start = $dateFrom->copy()->startOfDay();
            $end = $dateTo->copy()->endOfDay();
            $query['created_at_min'] = $start->toIso8601String();
            $query['created_at_max'] = $end->toIso8601String();
            $effectiveRangeStart = $start;
            $createdAtMinIso = $query['created_at_min'];
            $createdAtMaxIso = $query['created_at_max'];
            $daysForReport = null;
        } elseif ($daysWindow > 0) {
            $effectiveRangeStart = Carbon::now()->subDays($daysWindow);
            $query['created_at_min'] = $effectiveRangeStart->toIso8601String();
            $createdAtMinIso = $query['created_at_min'];
            $daysForReport = $daysWindow;
        } else {
            $daysForReport = 0;
        }

        [$syncOverlapWarning, $syncOverlapMessage] = $this->detectOlderRangeWarning($shopDomain, $effectiveRangeStart);

        $payloads = [];
        $pages = 0;
        $nextUrl = null;

        do {
            $response = $nextUrl
                ? $client->getAbsolute($nextUrl)
                : $client->get('orders.json', $query);

            if (! $response->successful()) {
                Log::error('Shopify orders API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException(
                    ShopifyAdminApiClient::formatAdminApiFailureMessage($response, 'فشل Pull Orders من Shopify')
                );
            }

            $pages++;
            $body = $response->json();
            $orders = isset($body['orders']) && is_array($body['orders']) ? $body['orders'] : [];

            foreach ($orders as $orderPayload) {
                if (is_array($orderPayload)) {
                    $payloads[] = $orderPayload;
                }
            }

            $nextUrl = ShopifyAdminApiClient::extractNextPageUrl($response->header('Link'));
        } while ($nextUrl !== null);

        if ($effectiveRangeStart !== null) {
            $this->rememberEarliestSyncedMin($shopDomain, $effectiveRangeStart);
        }

        return [
            'payloads' => $payloads,
            'shop_domain' => $shopDomain,
            'meta' => [
                'pages' => $pages,
                'days_window' => $daysForReport,
                'created_at_min' => $createdAtMinIso,
                'created_at_max' => $createdAtMaxIso,
                'sync_overlap_warning' => $syncOverlapWarning,
                'sync_overlap_message' => $syncOverlapMessage,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function importPayloads(array $payloads, string $shopDomain, array $meta): array
    {
        $imported = 0;
        $skippedExisting = 0;
        $failed = 0;
        $errors = [];

        $this->orderImportService->resetRunState();

        foreach ($payloads as $orderPayload) {
            $oid = isset($orderPayload['id']) ? (int) $orderPayload['id'] : null;

            if ($oid && Order::query()->where('shopify_order_id', $oid)->exists()) {
                $skippedExisting++;

                continue;
            }

            try {
                $this->orderImportService->import($orderPayload, $shopDomain !== '' ? $shopDomain : null);
                $imported++;
            } catch (\Throwable $e) {
                $persisted = $oid && Order::query()->where('shopify_order_id', $oid)->exists();
                if ($persisted) {
                    $imported++;
                    Log::warning('Shopify order sync: import error after order persisted', [
                        'shopify_order_id' => $oid,
                        'error' => $e->getMessage(),
                    ]);
                } else {
                    $failed++;
                    $errors[] = [
                        'shopify_order_id' => $oid,
                        'message' => $e->getMessage(),
                    ];
                    Log::warning('Shopify order sync: import failed', [
                        'shopify_order_id' => $oid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Shopify orders sync finished', [
            'imported' => $imported,
            'skipped_existing' => $skippedExisting,
            'failed' => $failed,
            'pages' => $meta['pages'] ?? null,
        ]);

        $createdCollectionCompanies = array_values($this->orderImportService->createdCollectionCompanies);

        return array_merge($meta, [
            'orders_fetched' => count($payloads),
            'imported' => $imported,
            'updated' => 0,
            'skipped_existing' => $skippedExisting,
            'skipped_duplicates' => 0,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
            'errors_truncated' => count($errors) > 50,
            'shop_domain' => $shopDomain,
            'created_collection_companies' => $createdCollectionCompanies,
            'created_collection_companies_count' => count($createdCollectionCompanies),
        ]);
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
                'تنبيه: تاريخ بداية هذه المزامنة أقدم من أقدم نطاق تاريخ سبقته مزامنة من هذا المتجر. الطلبات الموجودة مسبقاً لن تُحدَّث ولن تُنشأ نسخ مكررة — سيُذكر عددها فقط.',
            ];
        }

        return [false, null];
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
