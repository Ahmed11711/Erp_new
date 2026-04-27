<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Models\ShopifyWebhookLog;
use App\Services\Shopify\ShopifyWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function __construct(private ShopifyWebhookVerifier $verifier) {}

    public function handle(Request $request)
    {
        $raw = $request->getContent();
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $topic = (string) ($request->header('X-Shopify-Topic') ?? '');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');
        $webhookId = $request->header('X-Shopify-Webhook-Id');

        if (! $this->verifier->verify($raw, $hmac)) {
            Log::warning('Shopify webhook: invalid or missing HMAC');

            return response('Unauthorized', 401);
        }

        $allowed = config('services.shopify.allowed_shop_domains');
        if (is_array($allowed) && $allowed !== [] && $shopDomain && ! in_array($shopDomain, $allowed, true)) {
            Log::warning('Shopify webhook: shop domain not allowed', ['shop' => $shopDomain]);

            return response('Forbidden', 403);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('Bad Request', 400);
        }

        $resourceId = isset($payload['id']) ? (int) $payload['id'] : null;

        try {
            ShopifyWebhookLog::query()->create([
                'topic' => $topic,
                'shop_domain' => $shopDomain,
                'webhook_id' => is_string($webhookId) ? $webhookId : null,
                'resource_id' => $resourceId ?: null,
                'payload' => $raw,
            ]);
        } catch (\Throwable $e) {
            Log::error('Shopify webhook log insert failed', ['error' => $e->getMessage()]);
        }

        ProcessShopifyWebhookJob::dispatch($topic, $shopDomain, $payload, is_string($webhookId) ? $webhookId : null);

        return response('OK', 200);
    }
}
