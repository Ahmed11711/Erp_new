<?php

namespace App\Services\Shopify;

use App\Models\ShopifyProduct;
use Illuminate\Support\Facades\DB;

class ShopifyProductCatalogService
{
    public function upsertFromProductWebhook(array $payload, ?string $shopDomain): void
    {
        $productId = isset($payload['id']) ? (int) $payload['id'] : null;
        if (! $productId) {
            return;
        }

        $variants = $payload['variants'] ?? [];
        if ($variants === []) {
            return;
        }

        DB::transaction(function () use ($payload, $shopDomain, $productId, $variants) {
            $imageService = app(ShopifyProductImageService::class);

            foreach ($variants as $v) {
                $variantId = isset($v['id']) ? (int) $v['id'] : null;
                if (! $variantId) {
                    continue;
                }
                $inventoryItemId = isset($v['inventory_item_id']) ? (int) $v['inventory_item_id'] : null;
                $sku = isset($v['sku']) ? trim((string) $v['sku']) : '';
                $price = (float) ($v['price'] ?? 0);
                $title = (string) ($payload['title'] ?? '');
                $variantTitle = (string) ($v['title'] ?? '');
                $name = trim($title.($variantTitle !== '' && $variantTitle !== 'Default Title' ? ' — '.$variantTitle : ''));
                [$imageUrl1, $imageUrl2] = $imageService->extractImageUrls($payload, is_array($v) ? $v : null);

                ShopifyProduct::query()->updateOrCreate(
                    [
                        'shopify_product_id' => $productId,
                        'shopify_variant_id' => $variantId,
                    ],
                    [
                        'shop_domain' => $shopDomain,
                        'inventory_item_id' => $inventoryItemId,
                        'name' => $name !== '' ? $name : $title,
                        'sku' => $sku !== '' ? $sku : null,
                        'price' => $price,
                        'quantity' => (int) ($v['inventory_quantity'] ?? 0),
                        'image_url_1' => $imageUrl1,
                        'image_url_2' => $imageUrl2,
                    ]
                );
            }
        });
    }

    public function applyInventoryLevelUpdate(array $payload): void
    {
        $inventoryItemId = isset($payload['inventory_item_id']) ? (int) $payload['inventory_item_id'] : null;
        if (! $inventoryItemId) {
            return;
        }

        if (isset($payload['available']) && is_numeric($payload['available'])) {
            $available = (int) $payload['available'];
        } else {
            return;
        }

        ShopifyProduct::query()
            ->where('inventory_item_id', $inventoryItemId)
            ->update(['quantity' => $available]);
    }
}
