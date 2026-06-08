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

    /**
     * رسالة خطأ للمطوّر/المشرف مع تلميحات عربية شائعة لـ 401/403.
     */
    public static function formatAdminApiFailureMessage(Response $response, string $arabicPrefix): string
    {
        $status = $response->status();
        $json = $response->json();
        $detail = '';
        if (is_array($json)) {
            $errs = $json['errors'] ?? null;
            if (is_string($errs)) {
                $detail = trim($errs);
            } elseif (is_array($errs)) {
                $detail = json_encode($errs, JSON_UNESCAPED_UNICODE);
            }
        }
        if ($detail === '') {
            $body = (string) $response->body();
            $detail = strlen($body) > 500 ? substr($body, 0, 500).'…' : $body;
        }

        $hint = match ($status) {
            403 => ' غالباً: نطاقات Admin API للتطبيق لا تسمح بهذا الطلب (مثلاً read_products للمنتجات، read_orders و read_all_orders للطلبات القديمة)، أو أُضيفت الصلاحية بعد إنشاء التوكن؛ من Shopify: الإعدادات ← التطبيقات والقنوات ← تطوير التطبيقات ← تطبيقك ← تكامل Admin API ← فعّل الصلاحيات ثم انسخ **Admin API access token** جديداً وحدّث SHOPIFY_ADMIN_ACCESS_TOKEN في .env ثم php artisan config:clear.',
            401 => ' التوكن غير مقبول. تحقق من SHOPIFY_ADMIN_ACCESS_TOKEN وأن المتجر في SHOPIFY_SHOP_DOMAIN مطابق (مثل your-store.myshopify.com).',
            default => '',
        };

        return $arabicPrefix.' (HTTP '.$status.').'.$hint.' رد Shopify: '.$detail;
    }
}
