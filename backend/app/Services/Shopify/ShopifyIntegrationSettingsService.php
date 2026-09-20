<?php

namespace App\Services\Shopify;

use App\Models\Setting;

class ShopifyIntegrationSettingsService
{
    public const AUTO_IMPORT_ORDERS_KEY = 'shopify_auto_import_orders';

    public function autoImportOrdersEnabled(): bool
    {
        $stored = Setting::query()
            ->where('key', self::AUTO_IMPORT_ORDERS_KEY)
            ->value('value');

        if ($stored !== null && $stored !== '') {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.shopify.auto_import_orders', true);
    }

    public function setAutoImportOrders(bool $enabled): void
    {
        Setting::updateOrCreate(
            ['key' => self::AUTO_IMPORT_ORDERS_KEY],
            ['value' => $enabled ? '1' : '0']
        );
    }

    /**
     * @return array{auto_import_orders: bool}
     */
    public function toArray(): array
    {
        return [
            'auto_import_orders' => $this->autoImportOrdersEnabled(),
        ];
    }
}
