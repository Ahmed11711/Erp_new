<?php

namespace App\Services\Shopify;

use App\Models\Order;
use App\Models\ShopifyProduct;

/**
 * Application-facing facade for Shopify Admin operations used by jobs and controllers.
 */
class ShopifyService
{
    public function __construct(
        private ShopifyAdminApiClient $client,
        private ShopifyFulfillmentService $fulfillment
    ) {}

    public static function fromConfig(): self
    {
        $client = ShopifyAdminApiClient::fromConfig();

        return new self($client, new ShopifyFulfillmentService($client));
    }

    public function pushProductUpdate(ShopifyProduct $product): \Illuminate\Http\Client\Response
    {
        if (! $product->shopify_product_id || ! $product->shopify_variant_id) {
            throw new \InvalidArgumentException('Shopify product row is missing product or variant id.');
        }

        $body = [
            'product' => [
                'id' => $product->shopify_product_id,
                'variants' => [
                    [
                        'id' => $product->shopify_variant_id,
                        'price' => number_format((float) $product->price, 2, '.', ''),
                        'sku' => $product->sku,
                    ],
                ],
            ],
        ];

        return $this->client->put("products/{$product->shopify_product_id}.json", $body);
    }

    public function fulfillLocalOrder(Order $order): bool
    {
        return $this->fulfillment->fulfillShopifyOrder($order);
    }

    public function cancelLocalOrderOnShopify(Order $order): bool
    {
        return $this->fulfillment->cancelShopifyOrder($order);
    }
}
