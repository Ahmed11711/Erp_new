<?php

namespace App\Services\Shipping;

use App\Enums\CollectionProviderType;
use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Services\Shopify\ShopifyPaymentCollectionResolver;
use Illuminate\Support\Facades\Schema;

/**
 * يحدد شركة التحصيل (Paymob / Visa / …) المرتبطة بطلب — حتى لو فُقد الربط بعد الشحن.
 */
final class CollectionCompanyForOrderResolver
{
    public function __construct(
        private ShopifyPaymentCollectionResolver $shopifyResolver,
    ) {
    }

    public function resolve(Order $order, bool $persistLinkIfMissing = false): ?CollectionCompany
    {
        $order->loadMissing('order_details');
        $od = $order->order_details;
        if (! $od) {
            return null;
        }

        $company = $this->fromProviderFields($od);
        if ($company) {
            return $company;
        }

        $company = $this->fromShopifyGateway($order);
        if ($company) {
            if ($persistLinkIfMissing) {
                $this->persistProviderLink($od, $company);
            }

            return $company;
        }

        $company = $this->fromLegacyShippingLink($od);
        if ($company && $persistLinkIfMissing) {
            $this->persistProviderLink($od, $company);
        }

        return $company;
    }

    private function fromProviderFields(OrderDetails $od): ?CollectionCompany
    {
        if ($od->collection_provider_type !== CollectionProviderType::CollectionCompany->value
            || ! $od->collection_provider_id) {
            return null;
        }

        return CollectionCompany::find((int) $od->collection_provider_id);
    }

    private function fromShopifyGateway(Order $order): ?CollectionCompany
    {
        if (! Schema::hasColumn('orders', 'shopify_payment_gateway')) {
            return null;
        }

        $gateway = trim((string) ($order->shopify_payment_gateway ?? ''));
        if ($gateway === '') {
            return null;
        }

        return $this->shopifyResolver->findCompanyForGatewayLabel($gateway);
    }

    private function fromLegacyShippingLink(OrderDetails $od): ?CollectionCompany
    {
        if (! $od->collection_company_id) {
            return null;
        }

        return CollectionCompany::query()
            ->where('linked_shipping_company_id', (int) $od->collection_company_id)
            ->first();
    }

    private function persistProviderLink(OrderDetails $od, CollectionCompany $company): void
    {
        if ($od->collection_provider_type === CollectionProviderType::CollectionCompany->value
            && (int) $od->collection_provider_id === (int) $company->id) {
            return;
        }

        $od->collection_provider_type = CollectionProviderType::CollectionCompany->value;
        $od->collection_provider_id = (int) $company->id;
        if ($company->linked_shipping_company_id) {
            $od->collection_company_id = (int) $company->linked_shipping_company_id;
        }
        $od->save();
    }
}
