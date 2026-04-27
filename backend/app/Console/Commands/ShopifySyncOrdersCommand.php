<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyOrdersSyncService;
use Illuminate\Console\Command;

class ShopifySyncOrdersCommand extends Command
{
    protected $signature = 'shopify:sync-orders
                            {--days=30 : عدد الأيام للخلف (0 = كل الطلبات عبر الترقيم، قد يستغرق وقتاً)}';

    protected $description = 'استيراد طلبات Shopify غير الموجودة محلياً (يتطلب SHOPIFY_SHOP_DOMAIN و SHOPIFY_ADMIN_ACCESS_TOKEN و read_orders)';

    public function handle(ShopifyOrdersSyncService $syncService): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $days = 0;
        }
        if ($days > 3650) {
            $days = 3650;
        }

        if ($days === 0) {
            if (! $this->confirm('المزامنة بدون حد زمني تجلب كل الطلبات عبر كل الصفحات. المتابعة؟')) {
                return self::SUCCESS;
            }
        }

        try {
            $result = $syncService->sync($days);
            $this->info('المتجر: '.$result['shop_domain']);
            $this->info('نافذة الأيام: '.($result['days_window'] === 0 ? 'الكل' : (string) $result['days_window']));
            $this->info('صفحات API: '.$result['pages']);
            $this->info('مستورد: '.$result['imported']);
            $this->info('تخطي (موجود مسبقاً): '.$result['skipped_duplicates']);
            $this->info('فشل: '.$result['failed']);
            if ($result['failed'] > 0 && $result['errors'] !== []) {
                $this->warn('أول الأخطاء:');
                foreach (array_slice($result['errors'], 0, 10) as $err) {
                    $this->line('  #'.($err['shopify_order_id'] ?? '?').': '.$err['message']);
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
