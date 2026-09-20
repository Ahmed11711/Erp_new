<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyProductImageService
{
    /**
     * Extract up to two image CDN URLs from a Shopify Admin product payload.
     * Prefers the variant-linked image as photo1 when available.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>|null  $variant
     * @return array{0: ?string, 1: ?string}
     */
    public function extractImageUrls(array $product, ?array $variant = null): array
    {
        $images = [];
        foreach ($product['images'] ?? [] as $img) {
            if (! is_array($img)) {
                continue;
            }
            $src = isset($img['src']) ? trim((string) $img['src']) : '';
            if ($src === '') {
                continue;
            }
            $images[] = [
                'id' => isset($img['id']) ? (int) $img['id'] : null,
                'src' => $src,
            ];
        }

        if ($images === []) {
            // Some payloads expose a single image object.
            $single = $product['image']['src'] ?? null;
            if (is_string($single) && trim($single) !== '') {
                return [trim($single), null];
            }

            return [null, null];
        }

        $primary = null;
        $variantImageId = isset($variant['image_id']) ? (int) $variant['image_id'] : 0;
        if ($variantImageId > 0) {
            foreach ($images as $img) {
                if (($img['id'] ?? null) === $variantImageId) {
                    $primary = $img['src'];
                    break;
                }
            }
        }

        if ($primary === null) {
            $primary = $images[0]['src'];
        }

        $secondary = null;
        foreach ($images as $img) {
            if ($img['src'] !== $primary) {
                $secondary = $img['src'];
                break;
            }
        }

        return [$primary, $secondary];
    }

    /**
     * Download a remote image into public/images and return the stored filename.
     */
    public function downloadToPublicImages(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout(45)
                ->withHeaders(['Accept' => 'image/*,*/*'])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('shopify_image_download_failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) < 32) {
                return null;
            }

            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
            $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: '';
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $contentType = strtolower((string) $response->header('Content-Type'));
                $ext = match (true) {
                    str_contains($contentType, 'png') => 'png',
                    str_contains($contentType, 'webp') => 'webp',
                    str_contains($contentType, 'gif') => 'gif',
                    default => 'jpg',
                };
            }

            $name = time() . '_shopify_' . uniqid('', true) . '.' . $ext;
            $dest = public_path('images/' . $name);
            if (! is_dir(public_path('images'))) {
                mkdir(public_path('images'), 0755, true);
            }
            file_put_contents($dest, $body);

            return $name;
        } catch (\Throwable $e) {
            Log::warning('shopify_image_download_exception', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fetch product JSON from Shopify Admin and return image URLs for a variant.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function fetchImageUrlsFromApi(int $shopifyProductId, ?int $shopifyVariantId = null): array
    {
        if ($shopifyProductId < 1) {
            return [null, null];
        }

        try {
            $client = ShopifyAdminApiClient::fromConfig();
            $response = $client->get('products/'.$shopifyProductId.'.json');
            if (! $response->successful()) {
                return [null, null];
            }
            $product = $response->json('product');
            if (! is_array($product)) {
                return [null, null];
            }

            $variant = null;
            if ($shopifyVariantId) {
                foreach ($product['variants'] ?? [] as $v) {
                    if (is_array($v) && (int) ($v['id'] ?? 0) === $shopifyVariantId) {
                        $variant = $v;
                        break;
                    }
                }
            }

            return $this->extractImageUrls($product, $variant);
        } catch (\Throwable $e) {
            Log::warning('shopify_image_api_fetch_failed', [
                'product_id' => $shopifyProductId,
                'error' => $e->getMessage(),
            ]);

            return [null, null];
        }
    }
}
