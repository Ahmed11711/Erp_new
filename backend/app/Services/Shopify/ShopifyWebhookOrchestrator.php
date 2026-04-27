<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Log;

class ShopifyWebhookOrchestrator
{
    public function __construct(
        private ShopifyOrderImportService $orderImport,
        private ShopifyProductCatalogService $productCatalog
    ) {}

    public function process(string $topic, ?string $shopDomain, array $payload): void
    {
        match ($topic) {
            'orders/create' => $this->handleOrderCreate($payload, $shopDomain),
            'orders/updated' => $this->orderImport->syncFromUpdate($payload, $shopDomain),
            'products/create', 'products/update' => $this->productCatalog->upsertFromProductWebhook($payload, $shopDomain),
            'inventory_levels/update' => $this->productCatalog->applyInventoryLevelUpdate($payload),
            default => Log::debug('Shopify webhook topic ignored by orchestrator', ['topic' => $topic]),
        };
    }

    private function handleOrderCreate(array $payload, ?string $shopDomain): void
    {
        $this->orderImport->import($payload, $shopDomain);
    }
}
