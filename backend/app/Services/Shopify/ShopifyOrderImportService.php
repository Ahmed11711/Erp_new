<?php

namespace App\Services\Shopify;

use App\Jobs\SendOrderToShippingJob;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\ShopifyProductMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShopifyOrderImportService
{
    public function import(array $payload, ?string $shopDomain): ?Order
    {
        $shopifyOrderId = isset($payload['id']) ? (int) $payload['id'] : null;
        if (! $shopifyOrderId) {
            throw new \InvalidArgumentException('Shopify payload missing order id.');
        }

        if (Order::query()->where('shopify_order_id', $shopifyOrderId)->exists()) {
            Log::info('Shopify import skipped: duplicate order', ['shopify_order_id' => $shopifyOrderId]);

            return null;
        }

        $orderSourceId = (int) config('services.shopify.default_order_source_id');
        $shippingMethodId = (int) config('services.shopify.default_shipping_method_id');
        $fallbackCategoryId = (int) config('services.shopify.fallback_category_id');

        if ($orderSourceId < 1 || $shippingMethodId < 1 || $fallbackCategoryId < 1) {
            throw new \RuntimeException(
                'Shopify integration: set SHOPIFY_DEFAULT_ORDER_SOURCE_ID, SHOPIFY_DEFAULT_SHIPPING_METHOD_ID, and SHOPIFY_FALLBACK_CATEGORY_ID in .env.'
            );
        }

        $lineRows = $this->buildLineItems($payload, $shopDomain, $fallbackCategoryId);
        if ($lineRows === []) {
            throw new \RuntimeException('Shopify order has no importable line items.');
        }

        $shipping = $this->extractShippingCost($payload);
        $totals = $this->extractTotals($payload);

        $shippingAddr = $payload['shipping_address'] ?? [];
        $billingAddr = $payload['billing_address'] ?? [];
        $addr = $shippingAddr !== [] ? $shippingAddr : $billingAddr;

        $customerName = $this->buildCustomerName($addr, $payload);
        $phone = $this->resolvePhone($payload, $addr);
        if ($phone === '') {
            $phone = (string) config('services.shopify.placeholder_phone', '0000000000');
        }

        $financialStatus = (string) ($payload['financial_status'] ?? '');
        $fulfillmentStatus = (string) ($payload['fulfillment_status'] ?? '');

        $governorate = (string) ($addr['province'] ?? $addr['city'] ?? config('services.shopify.default_governorate', 'غير محدد'));
        $city = (string) ($addr['city'] ?? '');
        $address = trim(implode(' ', array_filter([
            $addr['address1'] ?? '',
            $addr['address2'] ?? '',
        ])));

        if ($address === '') {
            $address = (string) config('services.shopify.default_address', '-');
        }

        $orderDate = isset($payload['created_at'])
            ? Carbon::parse($payload['created_at'])->toDateString()
            : now()->toDateString();

        $referenceNumber = isset($payload['name']) ? (string) $payload['name'] : null;
        $currency = (string) ($payload['currency'] ?? '');
        $note = trim(sprintf(
            'Shopify %s | Order #%s | %s',
            $referenceNumber ?? '',
            $payload['order_number'] ?? '',
            $currency !== '' ? "Currency: {$currency}" : ''
        ));

        $trackingUserId = config('services.shopify.tracking_user_id');
        if ($trackingUserId === null || $trackingUserId < 1) {
            throw new \RuntimeException(
                'Shopify integration: set SHOPIFY_TRACKING_USER_ID to a valid users.id (used for order tracking history).'
            );
        }

        $order = DB::transaction(function () use (
            $shopifyOrderId,
            $shopDomain,
            $customerName,
            $phone,
            $governorate,
            $city,
            $address,
            $orderDate,
            $orderSourceId,
            $shippingMethodId,
            $shipping,
            $totals,
            $lineRows,
            $referenceNumber,
            $note,
            $trackingUserId,
            $financialStatus,
            $fulfillmentStatus
        ) {
            $order = Order::create([
                'shopify_order_id' => $shopifyOrderId,
                'shopify_shop_domain' => $shopDomain,
                'shopify_financial_status' => $financialStatus !== '' ? $financialStatus : null,
                'shopify_fulfillment_status' => $fulfillmentStatus !== '' ? $fulfillmentStatus : null,
                'customer_name' => $customerName,
                'customer_type' => (string) config('services.shopify.default_customer_type', 'فرد'),
                'customer_phone_1' => $phone,
                'customer_phone_2' => '',
                'tel' => null,
                'governorate' => $governorate,
                'city' => $city,
                'address' => $address,
                'order_date' => $orderDate,
                'shipping_method_id' => $shippingMethodId,
                'order_source_id' => $orderSourceId,
                'order_status' => 'طلب جديد',
                'order_type' => (string) config('services.shopify.order_type', 'جديد'),
                'shipping_cost' => $shipping,
                'total_invoice' => $totals['total_invoice'],
                'prepaid_amount' => $totals['prepaid_amount'],
                'discount' => $totals['discount'],
                'net_total' => $totals['net_total'],
                'vat' => $totals['vat'],
                'sales' => 0,
                'company_id' => null,
                'bank_id' => null,
                'order_notes' => null,
                'collect_note' => $note !== '' ? $note : null,
                'reference_number' => $referenceNumber,
            ]);

            $now = now();
            foreach ($lineRows as &$row) {
                $row['order_id'] = $order->id;
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            unset($row);
            OrderProduct::insert($lineRows);

            OrderDetails::updateOrCreate(
                ['order_id' => $order->id],
                []
            );

            $createdAt = now();
            DB::table('trackings')->insert([
                'order_id' => $order->id,
                'date' => $createdAt->toDateString(),
                'action' => 'طلب جديد (Shopify)',
                'user_id' => $trackingUserId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            Log::info('Shopify order imported', [
                'local_order_id' => $order->id,
                'shopify_order_id' => $shopifyOrderId,
            ]);

            return $order;
        });

        if ($order) {
            SendOrderToShippingJob::dispatch($order->id);
        }

        return $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(array $payload, ?string $shopDomain, int $fallbackCategoryId): array
    {
        $rows = [];
        foreach ($payload['line_items'] ?? [] as $line) {
            if (! empty($line['gift_card'])) {
                continue;
            }

            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $sku = isset($line['sku']) ? trim((string) $line['sku']) : '';
            $categoryId = $this->resolveCategoryId($variantId, $sku, $shopDomain, $fallbackCategoryId);

            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $unitPrice = (float) ($line['price'] ?? 0);
            $title = (string) ($line['title'] ?? '');
            $variantTitle = (string) ($line['variant_title'] ?? '');
            $special = trim($title.($variantTitle !== '' && $variantTitle !== 'Default Title' ? " — {$variantTitle}" : ''));

            $rows[] = [
                'category_id' => $categoryId,
                'shopify_line_item_id' => isset($line['id']) ? (int) $line['id'] : null,
                'shopify_variant_id' => $variantId,
                'quantity' => (string) $qty,
                'price' => $unitPrice,
                'total_price' => round($unitPrice * $qty, 2),
                'special_details' => $special,
            ];
        }

        return $rows;
    }

    private function resolveCategoryId(?int $variantId, string $sku, ?string $shopDomain, int $fallbackCategoryId): int
    {
        $q = ShopifyProductMapping::query()->where('active', true);

        if ($shopDomain) {
            $q->where(function ($sub) use ($shopDomain) {
                $sub->whereNull('shop_domain')->orWhere('shop_domain', $shopDomain);
            });
        }

        if ($variantId) {
            $byVariant = (clone $q)->where('shopify_variant_id', $variantId)->first();
            if ($byVariant) {
                return (int) $byVariant->category_id;
            }
        }

        if ($sku !== '') {
            $bySku = (clone $q)->where('sku', $sku)->first();
            if ($bySku) {
                return (int) $bySku->category_id;
            }
        }

        return $fallbackCategoryId;
    }

    private function extractShippingCost(array $payload): float
    {
        $sum = 0.0;
        foreach ($payload['shipping_lines'] ?? [] as $line) {
            if (isset($line['price_set']['shop_money']['amount'])) {
                $sum += (float) $line['price_set']['shop_money']['amount'];
            } elseif (isset($line['discounted_price_set']['shop_money']['amount'])) {
                $sum += (float) $line['discounted_price_set']['shop_money']['amount'];
            } elseif (isset($line['price'])) {
                $sum += (float) $line['price'];
            }
        }

        if ($sum > 0) {
            return round($sum, 2);
        }

        if (isset($payload['total_shipping_price_set']['shop_money']['amount'])) {
            return round((float) $payload['total_shipping_price_set']['shop_money']['amount'], 2);
        }

        return 0.0;
    }

    /**
     * @return array{total_invoice: float, discount: float, vat: float, net_total: float, prepaid_amount: float}
     */
    private function extractTotals(array $payload): array
    {
        $totalLineItems = (float) ($payload['total_line_items_price'] ?? 0);
        $subtotal = (float) ($payload['subtotal_price'] ?? $totalLineItems);
        $discount = (float) ($payload['total_discounts'] ?? 0);
        $tax = (float) ($payload['total_tax'] ?? 0);
        $netTotal = (float) ($payload['total_price'] ?? 0);

        $financialStatus = (string) ($payload['financial_status'] ?? 'pending');
        $prepaid = $financialStatus === 'paid' ? $netTotal : 0.0;

        $totalInvoice = $totalLineItems > 0 ? $totalLineItems : $subtotal;

        return [
            'total_invoice' => round($totalInvoice, 2),
            'discount' => round($discount, 2),
            'vat' => round($tax, 2),
            'net_total' => round($netTotal, 2),
            'prepaid_amount' => round($prepaid, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $addr
     */
    private function buildCustomerName(array $addr, array $payload): string
    {
        $first = trim((string) ($addr['first_name'] ?? ''));
        $last = trim((string) ($addr['last_name'] ?? ''));
        $name = trim($first.' '.$last);
        if ($name !== '') {
            return $name;
        }
        if (! empty($payload['customer']['first_name']) || ! empty($payload['customer']['last_name'])) {
            return trim(
                ($payload['customer']['first_name'] ?? '').' '.($payload['customer']['last_name'] ?? '')
            );
        }

        return (string) config('services.shopify.default_customer_name', 'عميل Shopify');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits !== '' ? $digits : '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $addr
     */
    private function resolvePhone(array $payload, array $addr): string
    {
        $candidates = [
            (string) ($addr['phone'] ?? ''),
            (string) ($payload['phone'] ?? ''),
            (string) data_get($payload, 'customer.phone'),
            (string) data_get($payload, 'customer.default_address.phone'),
            (string) data_get($payload, 'billing_address.phone'),
        ];
        foreach ($payload['note_attributes'] ?? [] as $na) {
            if (strtolower((string) ($na['name'] ?? '')) === 'phone') {
                $candidates[] = (string) ($na['value'] ?? '');
            }
        }
        foreach ($candidates as $c) {
            $n = $this->normalizePhone($c);
            if ($n !== '') {
                return $n;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function syncFromUpdate(array $payload, ?string $shopDomain): void
    {
        $shopifyOrderId = isset($payload['id']) ? (int) $payload['id'] : null;
        if (! $shopifyOrderId) {
            return;
        }

        $order = Order::query()->where('shopify_order_id', $shopifyOrderId)->first();
        if (! $order) {
            Log::info('Shopify orders/updated: no local order', ['shopify_order_id' => $shopifyOrderId]);

            return;
        }

        $totals = $this->extractTotals($payload);
        $shipping = $this->extractShippingCost($payload);
        $financialStatus = (string) ($payload['financial_status'] ?? '');
        $fulfillmentStatus = (string) ($payload['fulfillment_status'] ?? '');

        $order->update(array_filter([
            'shopify_shop_domain' => $shopDomain ?? $order->shopify_shop_domain,
            'shopify_financial_status' => $financialStatus !== '' ? $financialStatus : null,
            'shopify_fulfillment_status' => $fulfillmentStatus !== '' ? $fulfillmentStatus : null,
            'shipping_cost' => $shipping,
            'total_invoice' => $totals['total_invoice'],
            'prepaid_amount' => $totals['prepaid_amount'],
            'discount' => $totals['discount'],
            'net_total' => $totals['net_total'],
            'vat' => $totals['vat'],
        ], fn ($v) => $v !== null));

        Log::info('Shopify order updated from webhook', ['local_order_id' => $order->id]);
    }
}
