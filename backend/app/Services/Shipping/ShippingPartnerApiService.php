<?php

namespace App\Services\Shipping;

use App\DTO\Shipping\ShippingOrderPayloadDto;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingPartnerApiService
{
    public function submitOrder(ShippingOrderPayloadDto $dto): Response
    {
        $base = rtrim((string) config('services.shipping_partner.api_base_url'), '/');
        $token = (string) config('services.shipping_partner.api_token');

        if ($base === '') {
            throw new \RuntimeException('SHIPPING_API_BASE_URL is not configured.');
        }

        $payload = $dto->toArray();
        $req = Http::acceptJson()
            ->timeout((int) config('services.shipping_partner.timeout', 60))
            ->withHeaders(array_filter([
                'Authorization' => $token !== '' ? 'Bearer '.$token : null,
                'X-Integration' => 'erp-shopify',
            ]));

        Log::info('Shipping partner: submit order request', [
            'local_order_id' => $dto->localOrderId,
            'url' => $base.'/orders',
        ]);

        $response = $req->post($base.'/orders', $payload);

        Log::info('Shipping partner: submit order response', [
            'local_order_id' => $dto->localOrderId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }
}
