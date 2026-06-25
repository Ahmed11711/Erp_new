<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierPurgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * حذف الموردين وفواتير المشتريات وجميع السجلات المحاسبية والمالية المرتبطة —
 * للاختبار المحلي وإعادة التسجيل من الصفر.
 */
class PurgeSuppliersAndPurchasesCommand extends Command
{
    protected $signature = 'erp:purge-suppliers-purchases
                            {--force : تأكيد التنفيذ (إلزامي)}
                            {--yes : تخطي سؤال التأكيد}
                            {--skip-processing : الإبقاء على أوامر التشغيل الخارجي (قد يفشل حذف الموردين)}
                            {--reset-inventory : تصفير مخزون المخازن المعيارية بعد الحذف}';

    protected $description = 'حذف الموردين وفواتير المشتريات والحسابات المرتبطة (اختبار فقط)';

    public function handle(SupplierPurgeService $purgeService): int
    {
        if (! $this->option('force')) {
            $this->error('أضف --force لتنفيذ عملية خطيرة تمس الموردين وفواتير المشتريات.');

            return self::FAILURE;
        }

        $includeProcessing = ! $this->option('skip-processing') && Schema::hasTable('processing_orders');
        $preview = $purgeService->preview();

        $this->warn('=== حذف الموردين وفواتير المشتريات ===');
        $this->line("  موردين: {$preview['supplier_count']}");
        $this->line("  فواتير مشتريات: {$preview['purchase_count']}");
        if ($includeProcessing) {
            $this->line("  أوامر تشغيل خارجي: {$preview['processing_count']}");
        }
        $this->newLine();
        $this->warn('لن تُمس شركات الشحن/المندوبين أو الطلبات.');
        $this->warn('قيود المخزون الناتجة عن المشتريات تبقى ما لم تُفعّل --reset-inventory.');

        if (! $this->option('yes') && ! $this->confirm('هل تريد المتابعة؟')) {
            return self::SUCCESS;
        }

        $counts = $purgeService->purge($includeProcessing);

        if ($this->option('reset-inventory')) {
            $this->call('erp:reset-inventory-testing', ['--force' => true, '--skip-gl' => true]);
        }

        $this->newLine();
        $this->info('=== تم الحذف ===');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if (! $this->option('reset-inventory')) {
            $this->warn('لتصفير المخزون المتأثر بالمشتريات شغّل: php artisan erp:purge-suppliers-purchases --force --yes --reset-inventory');
        }

        return self::SUCCESS;
    }
}
