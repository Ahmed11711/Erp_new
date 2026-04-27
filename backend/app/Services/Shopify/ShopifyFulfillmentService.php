<?php

namespace App\Services\Shopify;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ShopifyFulfillmentService
{
    public function __construct(private ShopifyAdminApiClient $client) {}

    /**
     * Marks all open fulfillment orders for the Shopify order as fulfilled (delivered path).
     */
    public function fulfillShopifyOrder(Order $order): bool
    {
        $shopifyOrderId = $order->shopify_order_id;
        if (! $shopifyOrderId) {
            Log::warning('Shopify fulfill: order has no shopify_order_id', ['order_id' => $order->id]);

            return false;
        }

        $foResponse = $this->client->get("orders/{$shopifyOrderId}/fulfillment_orders.json");
        if (! $foResponse->successful()) {
            Log::error('Shopify fulfill: failed to load fulfillment_orders', [
                'order_id' => $order->id,
                'status' => $foResponse->status(),
                'body' => $foResponse->body(),
            ]);

            return false;
        }

        $fulfillmentOrders = $foResponse->json('fulfillment_orders') ?? [];
        $lineItemsByFulfillmentOrder = [];

        foreach ($fulfillmentOrders as $fo) {
            if (($fo['status'] ?? '') !== 'open') {
                continue;
            }
            $foId = $fo['id'] ?? null;
            if (! $foId) {
                continue;
            }
            $lineItems = [];
            foreach ($fo['line_items'] ?? [] as $li) {
                $liId = $li['id'] ?? null;
                $qty = (int) ($li['fulfillable_quantity'] ?? $li['quantity'] ?? 0);
                if ($liId && $qty > 0) {
                    $lineItems[] = ['id' => $liId, 'quantity' => $qty];
                }
            }
            if ($lineItems !== []) {
                $lineItemsByFulfillmentOrder[] = [
                    'fulfillment_order_id' => $foId,
                    'fulfillment_order_line_items' => $lineItems,
                ];
            }
        }

        if ($lineItemsByFulfillmentOrder === []) {
            Log::info('Shopify fulfill: no open fulfillment lines', ['shopify_order_id' => $shopifyOrderId]);

            return true;
        }

        $body = [
            'fulfillment' => [
                'notify_customer' => true,
                'line_items_by_fulfillment_order' => $lineItemsByFulfillmentOrder,
            ],
        ];

        $resp = $this->client->post('fulfillments.json', $body);
        if (! $resp->successful()) {
            Log::error('Shopify fulfill: fulfillments.json failed', [
                'order_id' => $order->id,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return false;
        }

        Log::info('Shopify fulfill: created fulfillment', ['order_id' => $order->id, 'shopify_order_id' => $shopifyOrderId]);

        return true;
    }

    /**
     * Cancels the order on Shopify (failed delivery path).
     */
    public function cancelShopifyOrder(Order $order, string $reason = 'other'): bool
    {
        $shopifyOrderId = $order->shopify_order_id;
        if (! $shopifyOrderId) {
            return false;
        }

        $resp = $this->client->post("orders/{$shopifyOrderId}/cancel.json", [
            'reason' => $reason,
        ]);

        if (! $resp->successful()) {
            Log::error('Shopify cancel order failed', [
                'order_id' => $order->id,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return false;
        }

        Log::info('Shopify order cancelled', ['order_id' => $order->id]);

        return true;
    }
}
