<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyOrdersSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ShopifySyncOrdersCommand extends Command
{
    protected $signature = 'shopify:sync-orders
                            {--days=30 : عدد الأيام للخلف عند عدم استخدام --from/--to (0 = كل الطلبات عبر الترقيم)}
                            {--from= : تاريخ بداية الإنشاء (YYYY-MM-DD)}
                            {--to= : تاريخ نهاية الإنشاء (YYYY-MM-DD)}';

    protected $description = 'استيراد طلبات Shopify الجديدة محلياً — الموجودة مسبقاً تُتخطى (يتطلب SHOPIFY_SHOP_DOMAIN و SHOPIFY_ADMIN_ACCESS_TOKEN و read_orders)';

    public function handle(ShopifyOrdersSyncService $syncService): int
    {
        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');
        $hasFrom = $fromOpt !== null && $fromOpt !== '';
        $hasTo = $toOpt !== null && $toOpt !== '';

        if ($hasFrom !== $hasTo) {
            $this->error('استخدم --from و --to معاً، أو اتركهما لاستخدام --days.');

            return self::FAILURE;
        }

        if ($hasFrom && $hasTo) {
            try {
                $dateFrom = Carbon::parse((string) $fromOpt)->startOfDay();
                $dateTo = Carbon::parse((string) $toOpt)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('تعذّر قراءة التاريخ: '.$e->getMessage());

                return self::FAILURE;
            }
            if ($dateFrom->greaterThan($dateTo)) {
                $this->error('تاريخ --from يجب أن يكون قبل أو يساوي --to.');

                return self::FAILURE;
            }
            $runSync = fn () => $syncService->sync(0, $dateFrom, $dateTo);
        } else {
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
            $runSync = fn () => $syncService->sync($days);
        }

        try {
            $result = $runSync();
            $this->info('المتجر: '.$result['shop_domain']);
            if ($result['created_at_min'] !== null) {
                $this->info('من: '.$result['created_at_min']);
            }
            if ($result['created_at_max'] !== null) {
                $this->info('إلى: '.$result['created_at_max']);
            }
            if ($result['days_window'] !== null) {
                $this->info('نافذة الأيام: '.($result['days_window'] === 0 ? 'الكل' : (string) $result['days_window']));
            }
            if (! empty($result['sync_overlap_warning'])) {
                $this->warn($result['sync_overlap_message'] ?? 'تنبيه: نطاق المزامنة أقدم من نطاق سابق.');
            }
            $this->info('صفحات API: '.$result['pages']);
            $this->info('مستورد (جديد): '.$result['imported']);
            $this->info('موجود مسبقاً (بدون تعديل): '.($result['skipped_existing'] ?? 0));
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
