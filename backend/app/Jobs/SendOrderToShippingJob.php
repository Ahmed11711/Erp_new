<?php

namespace App\Jobs;

use App\DTO\Shipping\ShippingOrderPayloadDto;
use App\Models\Order;
use App\Services\Shipping\ShippingPartnerApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderToShippingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public int $orderId) {}

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [15, 60, 180, 600];
    }

    public function handle(ShippingPartnerApiService $shipping): void
    {
        $base = (string) config('services.shipping_partner.api_base_url');
        if ($base === '') {
            Log::warning('SendOrderToShippingJob skipped: SHIPPING_API_BASE_URL not set', ['order_id' => $this->orderId]);

            return;
        }

        $order = Order::query()
            ->with(['order_products.category'])
            ->find($this->orderId);

        if (! $order) {
            Log::warning('SendOrderToShippingJob: order not found', ['order_id' => $this->orderId]);

            return;
        }

        if ($order->shopify_needs_product_review) {
            Log::warning('SendOrderToShippingJob skipped: order needs product review (unmatched Shopify products)', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $lineItems = [];
        foreach ($order->order_products as $op) {
            $sku = $op->category ? (string) ($op->category->ref ?? '') : '';
            $lineItems[] = [
                'sku' => (string) $sku,
                'title' => (string) ($op->special_details ?? ''),
                'quantity' => max(1, (int) $op->quantity),
                'unit_price' => (float) $op->price,
            ];
        }

        $dto = new ShippingOrderPayloadDto(
            localOrderId: $order->id,
            shopifyOrderId: $order->shopify_order_id ? (int) $order->shopify_order_id : null,
            reference: (string) ($order->reference_number ?? '#'.$order->id),
            customerName: (string) $order->customer_name,
            phone: (string) $order->customer_phone_1,
            address: (string) $order->address,
            city: (string) ($order->city ?? ''),
            governorate: (string) $order->governorate,
            totalPrice: (float) $order->net_total,
            financialStatus: (string) ($order->shopify_financial_status ?? ''),
            fulfillmentStatus: (string) ($order->shopify_fulfillment_status ?? ''),
            lineItems: $lineItems,
        );

        $response = $shipping->submitOrder($dto);

        if (! $response->successful()) {
            throw new \RuntimeException('Shipping API HTTP '.$response->status().': '.$response->body());
        }

        $json = $response->json();
        $tracking = is_array($json) ? ($json['tracking_number'] ?? $json['trackingNumber'] ?? null) : null;
        $status = is_array($json) ? ($json['status'] ?? $json['shipping_status'] ?? 'submitted') : 'submitted';

        $order->update(array_filter([
            'shipping_tracking_number' => is_string($tracking) ? $tracking : null,
            'shipping_partner_status' => is_string($status) ? $status : 'submitted',
        ], fn ($v) => $v !== null));

        Log::info('SendOrderToShippingJob completed', ['order_id' => $order->id]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('SendOrderToShippingJob failed', [
            'order_id' => $this->orderId,
            'message' => $e?->getMessage(),
        ]);
    }
}
