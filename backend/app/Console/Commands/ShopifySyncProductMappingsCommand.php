<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyProductMappingSyncService;
use Illuminate\Console\Command;

class ShopifySyncProductMappingsCommand extends Command
{
    protected $signature = 'shopify:sync-product-mappings';

    protected $description = 'جلب منتجات Shopify وتحديث جدول shopify_product_mappings (يتطلب SHOPIFY_SHOP_DOMAIN و SHOPIFY_ADMIN_ACCESS_TOKEN)';

    public function handle(): int
    {
        try {
            $result = app(ShopifyProductMappingSyncService::class)->sync();

            $this->info('المتجر: '.$result['shop_domain']);
            $this->info('صفحات مسترجعة: '.$result['pages_fetched']);
            $this->info('متغيرات تمت معالجتها: '.$result['synced_variants']);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
