<?php

namespace App\Console\Commands;

use App\Services\Shopify\ShopifyProductMappingSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ShopifySyncProductMappingsCommand extends Command
{
    protected $signature = 'shopify:sync-product-mappings
                            {--days= : حدّ created_at_min بالأيام للخلف (بدون --from/--to)}
                            {--from= : تاريخ بداية إنشاء المنتج في Shopify (YYYY-MM-DD)}
                            {--to= : تاريخ نهاية (YYYY-MM-DD)}
                            {--v|verbose : عرض عينة من المتغيرات المزامنة في الطرفية}';

    protected $description = 'جلب منتجات Shopify وتحديث جدول shopify_product_mappings (يتطلب SHOPIFY_SHOP_DOMAIN و SHOPIFY_ADMIN_ACCESS_TOKEN)';

    public function handle(): int
    {
        $fromOpt = $this->option('from');
        $toOpt = $this->option('to');
        $hasFrom = $fromOpt !== null && $fromOpt !== '';
        $hasTo = $toOpt !== null && $toOpt !== '';

        if ($hasFrom !== $hasTo) {
            $this->error('استخدم --from و --to معاً، أو استخدم --days، أو بلا خيارات لجلب الكل.');

            return self::FAILURE;
        }

        try {
            $sync = app(ShopifyProductMappingSyncService::class);

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
                $result = $sync->sync($dateFrom, $dateTo);
            } elseif ($this->option('days') !== null && $this->option('days') !== '') {
                $days = (int) $this->option('days');
                if ($days < 0) {
                    $days = 0;
                }
                if ($days > 3650) {
                    $days = 3650;
                }
                $result = $days === 0 ? $sync->sync() : $sync->sync(null, null, $days);
            } else {
                $result = $sync->sync();
            }

            $this->info('المتجر: '.$result['shop_domain']);
            if ($result['created_at_min'] !== null) {
                $this->info('created_at من: '.$result['created_at_min']);
            }
            if ($result['created_at_max'] !== null) {
                $this->info('created_at إلى: '.$result['created_at_max']);
            }
            if ($result['days_window'] !== null) {
                $this->info('نافذة الأيام: '.(string) $result['days_window']);
            }
            if (! empty($result['sync_overlap_warning'])) {
                $this->warn($result['sync_overlap_message'] ?? 'تنبيه: نطاق أقدم من مزامنة سابقة.');
            }
            $this->info('صفحات مسترجعة: '.$result['pages_fetched']);
            $this->info('متغيرات تمت معالجتها: '.$result['synced_variants'].' (جديد: '.($result['variants_created'] ?? 0).'، تحديث: '.($result['variants_updated'] ?? 0).')');

            if ($this->option('verbose') && ! empty($result['sync_detail_rows'])) {
                $this->line('عينة من المزامنة (محدود بحد الـ API):');
                foreach ($result['sync_detail_rows'] as $row) {
                    $sku = $row['sku'] ?? '—';
                    $p = $row['product_title'] ?? '';
                    $lbl = $row['variant_label'] ?? '';
                    $act = ($row['action'] ?? '') === 'created' ? 'جديد' : 'تحديث';
                    $this->line(sprintf(
                        '  [%s] متغير %s | منتج: %s %s | SKU: %s | تصنيف: %s',
                        $act,
                        $row['shopify_variant_id'] ?? '?',
                        $p,
                        $lbl !== '' ? '('.$lbl.')' : '',
                        $sku,
                        $row['category_id'] ?? '?'
                    ));
                }
                if (! empty($result['sync_detail_truncated'])) {
                    $this->warn('قائمة العرض مقطوعة؛ راجع واجهة الإعدادات أو الـ API للحد الأقصى المعروض.');
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
