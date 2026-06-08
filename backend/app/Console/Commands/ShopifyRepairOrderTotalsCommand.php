<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Shopify\ShopifyAdminApiClient;
use App\Services\Shopify\ShopifyOrderImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShopifyRepairOrderTotalsCommand extends Command
{
    protected $signature = 'shopify:repair-order-totals
                            {--dry-run : عرض التغييرات دون حفظ}
                            {--only-paid : إصلاح الطلبات ذات shopify_financial_status=paid فقط}';

    protected $description = 'إعادة حساب net_total و prepaid_amount لطلبات Shopify المستوردة سابقاً من Admin API';

    public function handle(ShopifyOrderImportService $importService): int
    {
        $client = ShopifyAdminApiClient::fromConfig();
        $shopDomain = $client->normalizedShopHost();
        $dryRun = (bool) $this->option('dry-run');
        $onlyPaid = (bool) $this->option('only-paid');

        $query = Order::query()
            ->whereNotNull('shopify_order_id')
            ->orderBy('id');

        if ($onlyPaid) {
            $query->where('shopify_financial_status', 'paid');
        }

        $orders = $query->get(['id', 'shopify_order_id', 'customer_name', 'net_total', 'prepaid_amount', 'shopify_financial_status']);

        if ($orders->isEmpty()) {
            $this->info('لا توجد طلبات Shopify لإصلاحها.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $shopifyId = (int) $order->shopify_order_id;
            $response = $client->get("orders/{$shopifyId}.json");

            if (! $response->successful()) {
                $failed++;
                $this->warn("فشل جلب Shopify #{$shopifyId} (محلي {$order->id}): HTTP {$response->status()}");

                continue;
            }

            $payload = $response->json('order');
            if (! is_array($payload)) {
                $failed++;
                $this->warn("حمولة غير صالحة لـ Shopify #{$shopifyId}");

                continue;
            }

            $beforeNet = (float) $order->net_total;
            $beforePrepaid = (float) $order->prepaid_amount;

            if ($dryRun) {
                $ref = new \ReflectionClass($importService);
                $method = $ref->getMethod('extractTotals');
                $method->setAccessible(true);
                $totals = $method->invoke($importService, $payload);
                $afterNet = (float) $totals['net_total'];
                $afterPrepaid = (float) $totals['prepaid_amount'];
            } else {
                $fresh = $importService->applyFinancialFieldsFromPayload($order, $payload, $shopDomain);
                $afterNet = (float) $fresh->net_total;
                $afterPrepaid = (float) $fresh->prepaid_amount;
            }

            if (abs($beforeNet - $afterNet) > 0.009 || abs($beforePrepaid - $afterPrepaid) > 0.009) {
                $fixed++;
                $this->line(sprintf(
                    'طلب %d (%s) | net: %s → %s | prepaid: %s → %s | Shopify: %s',
                    $order->id,
                    $order->customer_name,
                    $beforeNet,
                    $afterNet,
                    $beforePrepaid,
                    $afterPrepaid,
                    $payload['financial_status'] ?? '?'
                ));
            }
        }

        $this->info(($dryRun ? '[معاينة] ' : '')."تم فحص {$orders->count()} طلب، تعديل {$fixed}، فشل {$failed}.");

        if ($dryRun && $fixed > 0) {
            $this->comment('شغّل بدون --dry-run لتطبيق التعديلات.');
        }

        Log::info('Shopify repair order totals finished', [
            'checked' => $orders->count(),
            'fixed' => $fixed,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
