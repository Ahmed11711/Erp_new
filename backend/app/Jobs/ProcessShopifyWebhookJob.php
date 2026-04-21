<?php

namespace App\Jobs;

use App\Services\Shopify\ShopifyWebhookOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessShopifyWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        public string $topic,
        public ?string $shopDomain,
        public array $payload,
        public ?string $webhookId = null,
    ) {}

    /**
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(ShopifyWebhookOrchestrator $orchestrator): void
    {
        if (is_string($this->webhookId) && $this->webhookId !== '') {
            if (DB::table('shopify_webhook_receipts')->where('webhook_id', $this->webhookId)->exists()) {
                return;
            }
        }

        $accepted = config('services.shopify.accepted_topics', []);
        if ($accepted !== [] && ! in_array($this->topic, $accepted, true)) {
            return;
        }

        try {
            $orchestrator->process($this->topic, $this->shopDomain, $this->payload);

            if (is_string($this->webhookId) && $this->webhookId !== '') {
                $now = now();
                DB::table('shopify_webhook_receipts')->insertOrIgnore([
                    'webhook_id' => $this->webhookId,
                    'shop_domain' => $this->shopDomain,
                    'topic' => $this->topic,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Shopify webhook job failed', [
                'topic' => $this->topic,
                'shop' => $this->shopDomain,
                'error' => $e->getMessage(),
                'resource_id' => $this->payload['id'] ?? null,
            ]);
            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        Log::error('Shopify webhook job permanently failed', [
            'topic' => $this->topic,
            'message' => $e?->getMessage(),
        ]);
    }
}
