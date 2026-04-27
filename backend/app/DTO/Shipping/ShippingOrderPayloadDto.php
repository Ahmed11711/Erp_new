<?php

namespace App\DTO\Shipping;

/**
 * Normalized payload sent to the external shipping partner API.
 *
 * @phpstan-type LineItem array{sku: string, title: string, quantity: int, unit_price: float}
 */
final class ShippingOrderPayloadDto
{
    /**
     * @param  LineItem[]  $lineItems
     */
    public function __construct(
        public int $localOrderId,
        public ?int $shopifyOrderId,
        public string $reference,
        public string $customerName,
        public string $phone,
        public string $address,
        public string $city,
        public string $governorate,
        public float $totalPrice,
        public string $financialStatus,
        public string $fulfillmentStatus,
        public array $lineItems,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'local_order_id' => $this->localOrderId,
            'shopify_order_id' => $this->shopifyOrderId,
            'reference' => $this->reference,
            'customer' => [
                'name' => $this->customerName,
                'phone' => $this->phone,
            ],
            'shipping_address' => [
                'line1' => $this->address,
                'city' => $this->city,
                'province' => $this->governorate,
            ],
            'totals' => [
                'total' => $this->totalPrice,
            ],
            'shopify' => [
                'financial_status' => $this->financialStatus,
                'fulfillment_status' => $this->fulfillmentStatus,
            ],
            'line_items' => $this->lineItems,
        ];
    }
}
