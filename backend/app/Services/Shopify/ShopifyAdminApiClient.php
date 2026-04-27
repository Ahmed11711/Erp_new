<?php

namespace App\Services\Shopify;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ShopifyAdminApiClient
{
    public function __construct(
        private string $shopDomain,
        private string $accessToken,
        private string $apiVersion
    ) {}

    public static function fromConfig(): self
    {
        $shop = (string) config('services.shopify.shop_domain');
        $token = (string) config('services.shopify.admin_access_token');
        $version = (string) config('services.shopify.api_version', '2024-10');

        if ($shop === '' || $token === '') {
            throw new \RuntimeException(
                'تعيين SHOPIFY_SHOP_DOMAIN و SHOPIFY_ADMIN_ACCESS_TOKEN في ملف .env لاستخدام Admin API.'
            );
        }

        return new self($shop, $token, $version);
    }

    public function normalizedShopHost(): string
    {
        $shop = str_replace(['https://', 'http://'], '', $this->shopDomain);

        return rtrim($shop, '/');
    }

    public function baseUri(): string
    {
        return 'https://'.$this->normalizedShopHost().'/admin/api/'.$this->apiVersion;
    }

    /**
     * @param  array<string, scalar|array|null>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        $url = $this->baseUri().'/'.ltrim($path, '/');

        return $this->http()->get($url, $query);
    }

    public function getAbsolute(string $url): Response
    {
        return $this->http()->get($url);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function post(string $path, array $json = []): Response
    {
        $url = $this->baseUri().'/'.ltrim($path, '/');

        return $this->http()->post($url, $json);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function put(string $path, array $json = []): Response
    {
        $url = $this->baseUri().'/'.ltrim($path, '/');

        return $this->http()->put($url, $json);
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function http()
    {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout((int) config('services.shopify.http_timeout', 120));
    }

    public static function extractNextPageUrl(?string $linkHeader): ?string
    {
        if ($linkHeader === null || $linkHeader === '') {
            return null;
        }

        if (preg_match('/<([^>]+)>;\s*rel="next"/', $linkHeader, $m)) {
            return $m[1];
        }

        return null;
    }
}
