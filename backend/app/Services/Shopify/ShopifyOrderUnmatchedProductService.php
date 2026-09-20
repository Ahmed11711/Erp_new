<?php

namespace App\Services\Shopify;

/**
 * يكتشف بنود Shopify غير المربوطة بصنف ERP (تُستخدم صنف fallback فقط).
 */
class ShopifyOrderUnmatchedProductService
{
    public function __construct(
        private ShopifyOrderImportService $orderImport,
        private ShopifyProductMappingSyncService $mappingSync,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectFromPayload(array $payload, ?string $shopDomain): array
    {
        $fallbackCategoryId = $this->orderImport->getFallbackCategoryId();
        $found = [];

        foreach ($payload['line_items'] ?? [] as $line) {
            if (! is_array($line) || ! empty($line['gift_card'])) {
                continue;
            }

            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $sku = isset($line['sku']) ? trim((string) $line['sku']) : '';
            $title = (string) ($line['title'] ?? '');
            $variantTitle = (string) ($line['variant_title'] ?? '');

            if (! $this->orderImport->isLineItemUnmatched($variantId, $sku, $shopDomain, $title, $variantTitle)) {
                continue;
            }

            $variantLabel = $variantTitle !== '' && strtolower($variantTitle) !== 'default title'
                ? $variantTitle
                : '';
            $expectedName = $this->mappingSync->normalizeShopifyNameToErp($title, $variantLabel);
            $displayName = trim($title.($variantLabel !== '' ? " — {$variantLabel}" : ''));
            $price = round((float) ($line['price'] ?? 0), 2);
            $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;

            $dedupeKey = $variantId !== null && $variantId > 0
                ? 'v:'.$variantId
                : 'h:'.md5(mb_strtolower($displayName).'|'.$sku);

            if (isset($found[$dedupeKey])) {
                $found[$dedupeKey]['order_count'] = (int) ($found[$dedupeKey]['order_count'] ?? 1) + 1;

                continue;
            }

            $found[$dedupeKey] = [
                'shopify_variant_id' => $variantId,
                'shopify_product_id' => $productId,
                'shopify_name' => $displayName,
                'expected_erp_name' => $expectedName !== '' ? $expectedName : $displayName,
                'product_title' => $title,
                'variant_label' => $variantLabel,
                'sku' => $sku !== '' ? $sku : null,
                'price' => $price,
                'order_count' => 1,
            ];
        }

        return array_values($found);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<int, array<string, mixed>>
     */
    public function mergeFromPayloads(array $payloads, ?string $shopDomain): array
    {
        $merged = [];

        foreach ($payloads as $payload) {
            if (! is_array($payload)) {
                continue;
            }
            foreach ($this->collectFromPayload($payload, $shopDomain) as $row) {
                $variantId = $row['shopify_variant_id'] ?? null;
                $key = $variantId !== null && (int) $variantId > 0
                    ? 'v:'.(int) $variantId
                    : 'h:'.md5(mb_strtolower((string) ($row['shopify_name'] ?? '')).'|'.($row['sku'] ?? ''));

                if (isset($merged[$key])) {
                    $merged[$key]['order_count'] = (int) ($merged[$key]['order_count'] ?? 1)
                        + (int) ($row['order_count'] ?? 1);

                    continue;
                }

                $merged[$key] = $row;
            }
        }

        return array_values($merged);
    }
}
