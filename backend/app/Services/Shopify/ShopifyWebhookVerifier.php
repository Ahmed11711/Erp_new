<?php

namespace App\Services\Shopify;

class ShopifyWebhookVerifier
{
    public function verify(string $rawBody, ?string $hmacHeader): bool
    {
        $secret = config('services.shopify.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return false;
        }
        if (! is_string($hmacHeader) || $hmacHeader === '') {
            return false;
        }

        $calculated = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($hmacHeader, $calculated);
    }
}
