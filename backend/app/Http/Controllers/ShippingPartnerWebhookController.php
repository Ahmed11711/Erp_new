<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Shopify\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingPartnerWebhookController extends Controller
{
    public function update(Request $request)
    {
        $expected = (string) config('services.shipping_partner.webhook_secret');
        if ($expected === '') {
            abort(503, 'Shipping webhook secret not configured.');
        }

        $bearer = (string) ($request->bearerToken() ?? '');
        $headerSecret = (string) $request->header('X-Shipping-Webhook-Secret', '');
        if (! hash_equals($expected, $bearer) && ! hash_equals($expected, $headerSecret)) {
            abort(401, 'Invalid shipping webhook credentials.');
        }

        $data = $request->validate([
            'tracking_number' => ['required', 'string', 'max:191'],
            'status' => ['required', 'string', 'max:64'],
        ]);

        $order = Order::query()
            ->where('shipping_tracking_number', $data['tracking_number'])
            ->first();

        if (! $order) {
            Log::warning('Shipping webhook: unknown tracking_number', ['tracking_number' => $data['tracking_number']]);

            return response()->json(['message' => 'Order not found for tracking number.'], 404);
        }

        $normalized = strtolower(trim($data['status']));
        $order->shipping_partner_status = $data['status'];

        $shopify = ShopifyService::fromConfig();

        if (str_contains($normalized, 'deliver')) {
            $order->shopify_fulfillment_status = 'fulfilled';
            $order->save();
            $ok = $shopify->fulfillLocalOrder($order->fresh());
            if (! $ok) {
                return response()->json(['message' => 'Local order updated but Shopify fulfillment failed.'], 422);
            }

            return response()->json(['message' => 'Order marked delivered; Shopify fulfillment submitted.']);
        }

        if (str_contains($normalized, 'fail') || str_contains($normalized, 'cancel')) {
            $order->shopify_fulfillment_status = 'cancelled';
            $order->order_status = 'ملغي';
            $order->save();
            $ok = $shopify->cancelLocalOrderOnShopify($order->fresh());
            if (! $ok) {
                return response()->json(['message' => 'Local order updated but Shopify cancel failed.'], 422);
            }

            return response()->json(['message' => 'Order marked failed/cancelled; Shopify cancel submitted.']);
        }

        $order->save();

        return response()->json(['message' => 'Status recorded.', 'shopify_action' => 'none']);
    }
}
