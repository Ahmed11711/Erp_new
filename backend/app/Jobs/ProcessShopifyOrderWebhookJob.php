<?php

namespace App\Jobs;

use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessShopifyOrderWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $topic,
        public ?string $shopDomain,
        public array $payload
    ) {}

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 60, 120];
    }

    public function handle(ShopifyOrderImportService $importService): void
    {
        $accepted = config('services.shopify.accepted_topics', ['orders/create']);
        if (! in_array($this->topic, $accepted, true)) {
            return;
        }

        try {
            $importService->import($this->payload, $this->shopDomain);
        } catch (\Throwable $e) {
            Log::error('Shopify webhook processing failed', [
                'topic' => $this->topic,
                'shop' => $this->shopDomain,
                'error' => $e->getMessage(),
                'shopify_order_id' => $this->payload['id'] ?? null,
            ]);
            throw $e;
        }
    }
}
