<?php

namespace App\Services\Shopify;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ShopifyOrdersSyncService
{
    public function __construct(
        private ShopifyOrderImportService $orderImportService
    ) {}

    /**
     * جلب طلبات Shopify واستيراد ما لا يوجد محلياً (حسب shopify_order_id).
     *
     * @param  int  $daysWindow  عدد الأيام للخلف من الآن؛ 0 = بدون حد أدنى للتاريخ (كل الصفحات — قد يكون بطيئاً).
     * @return array{imported: int, skipped_duplicates: int, failed: int, errors: array<int, array{shopify_order_id: int|null, message: string}>, errors_truncated: bool, pages: int, days_window: int, shop_domain: string}
     */
    public function sync(int $daysWindow): array
    {
        $client = ShopifyAdminApiClient::fromConfig();
        $shopDomain = $client->normalizedShopHost();

        $query = ['limit' => 250, 'status' => 'any'];
        if ($daysWindow > 0) {
            $query['created_at_min'] = Carbon::now()->subDays($daysWindow)->toIso8601String();
        }

        $imported = 0;
        $skippedDuplicates = 0;
        $failed = 0;
        $errors = [];
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
                    'فشل جلب الطلبات من Shopify (HTTP '.$response->status().'). تحقق من صلاحيات read_orders و read_all_orders (للطلبات الأقدم من 60 يوماً تقريباً).'
                );
            }

            $pages++;
            $body = $response->json();
            $orders = isset($body['orders']) && is_array($body['orders']) ? $body['orders'] : [];

            foreach ($orders as $orderPayload) {
                if (! is_array($orderPayload)) {
                    continue;
                }
                $oid = isset($orderPayload['id']) ? (int) $orderPayload['id'] : null;
                try {
                    $result = $this->orderImportService->import($orderPayload, $shopDomain);
                    if ($result === null) {
                        $skippedDuplicates++;
                    } else {
                        $imported++;
                    }
                } catch (\Throwable $e) {
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

            $nextUrl = ShopifyAdminApiClient::extractNextPageUrl($response->header('Link'));
        } while ($nextUrl !== null);

        Log::info('Shopify orders sync finished', [
            'imported' => $imported,
            'skipped_duplicates' => $skippedDuplicates,
            'failed' => $failed,
            'pages' => $pages,
            'days_window' => $daysWindow,
        ]);

        return [
            'imported' => $imported,
            'skipped_duplicates' => $skippedDuplicates,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 50),
            'errors_truncated' => count($errors) > 50,
            'pages' => $pages,
            'days_window' => $daysWindow,
            'shop_domain' => $shopDomain,
        ];
    }
}
