<?php

namespace App\Jobs;

use App\Models\ShopifyProduct;
use App\Services\Shopify\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateProductToShopifyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public function __construct(public int $shopifyProductRowId) {}

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [20, 120, 300];
    }

    public function handle(): void
    {
        $product = ShopifyProduct::query()->find($this->shopifyProductRowId);
        if (! $product) {
            return;
        }

        $service = ShopifyService::fromConfig();
        $response = $service->pushProductUpdate($product);

        if (! $response->successful()) {
            throw new \RuntimeException('Shopify product update failed: HTTP '.$response->status().' '.$response->body());
        }

        $product->forceFill(['last_pushed_to_shopify_at' => now()])->save();

        Log::info('UpdateProductToShopifyJob completed', ['shopify_products.id' => $product->id]);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('UpdateProductToShopifyJob failed', [
            'shopify_products.id' => $this->shopifyProductRowId,
            'message' => $e?->getMessage(),
        ]);
    }
}
