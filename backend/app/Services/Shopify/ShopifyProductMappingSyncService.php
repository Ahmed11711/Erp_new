<?php

namespace App\Services\Shopify;

use App\Models\ShopifyProductMapping;
use Illuminate\Support\Facades\Log;

class ShopifyProductMappingSyncService
{
    /**
     * يجلب منتجات Shopify (مع المتغيرات) ويحدّث جدول shopify_product_mappings.
     * التصنيف category_id للمتغيرات الجديدة = SHOPIFY_FALLBACK_CATEGORY_ID؛ الموجود يُحافَظ عليه.
     *
     * @return array{synced_variants: int, pages_fetched: int, shop_domain: string}
     */
    public function sync(): array
    {
        $client = ShopifyAdminApiClient::fromConfig();

        $fallbackCategoryId = (int) config('services.shopify.fallback_category_id');
        if ($fallbackCategoryId < 1) {
            throw new \RuntimeException(
                'Shopify: عيّن SHOPIFY_FALLBACK_CATEGORY_ID (تصنيف افتراضي لربط أصناف المتجر).'
            );
        }

        $shopDomain = $client->normalizedShopHost();
        $nextUrl = null;
        $pages = 0;
        $synced = 0;

        do {
            $response = $nextUrl
                ? $client->getAbsolute($nextUrl)
                : $client->get('products.json', ['limit' => 250]);

            if (! $response->successful()) {
                Log::error('Shopify products API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException(
                    'فشل طلب منتجات Shopify (HTTP '.$response->status().'). تحقق من الصلاحيات read_products والتوكن.'
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
                foreach ($product['variants'] ?? [] as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }
                    $variantId = isset($variant['id']) ? (int) $variant['id'] : null;
                    if (! $variantId) {
                        continue;
                    }
                    $sku = isset($variant['sku']) ? trim((string) $variant['sku']) : '';

                    $existing = ShopifyProductMapping::query()
                        ->where('shopify_variant_id', $variantId)
                        ->first();

                    $categoryId = $existing ? (int) $existing->category_id : $fallbackCategoryId;

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
                    $synced++;
                }
            }

            $nextUrl = ShopifyAdminApiClient::extractNextPageUrl($response->header('Link'));
        } while ($nextUrl !== null);

        Log::info('Shopify product mappings synced', [
            'shop' => $shopDomain,
            'synced_variants' => $synced,
            'pages' => $pages,
        ]);

        return [
            'synced_variants' => $synced,
            'pages_fetched' => $pages,
            'shop_domain' => $shopDomain,
        ];
    }
}
