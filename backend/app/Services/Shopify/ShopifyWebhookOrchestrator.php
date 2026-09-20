<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Log;

class ShopifyWebhookOrchestrator
{
    public function __construct(
        private ShopifyOrderImportService $orderImport,
        private ShopifyProductCatalogService $productCatalog,
        private ShopifyIntegrationSettingsService $integrationSettings,
    ) {}

    public function process(string $topic, ?string $shopDomain, array $payload): void
    {
        if (in_array($topic, ['orders/create', 'orders/updated'], true)
            && ! $this->integrationSettings->autoImportOrdersEnabled()) {
            Log::info('Shopify order webhook skipped: auto import disabled', [
                'topic' => $topic,
                'shopify_order_id' => $payload['id'] ?? null,
            ]);

            return;
        }

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
