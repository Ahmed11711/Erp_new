<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SalesOrderAccountingService;
use Illuminate\Console\Command;

/**
 * إعادة بناء قيد فاتورة طلبات الصيانة لترحيل الإيراد على «إيرادات الصيانة»
 * بدل «إيرادات المبيعات (بضاعة)». لا يمس قيود التحصيل.
 *
 * php artisan accounting:reclassify-maintenance-revenue --dry-run
 * php artisan accounting:reclassify-maintenance-revenue --from=2026-01-01 --to=2026-09-03
 * php artisan accounting:reclassify-maintenance-revenue --order=12345
 */
class ReclassifyMaintenanceRevenueCommand extends Command
{
    protected $signature = 'accounting:reclassify-maintenance-revenue
        {--dry-run : عرض العدد فقط دون تعديل}
        {--from= : تاريخ الطلب من (Y-m-d)}
        {--to= : تاريخ الطلب إلى (Y-m-d)}
        {--order= : معالجة طلب واحد فقط}
        {--limit=2000 : أقصى عدد طلبات في التشغيل الواحد}';

    protected $description = 'إعادة تصنيف إيراد طلبات الصيانة من مبيعات البضاعة إلى حساب إيرادات الصيانة';

    public function handle(
        SalesOrderAccountingService $salesAccounting,
        AccountingService $accounting
    ): int {
        $maintenanceAcc = TreeAccount::resolveMaintenanceRevenueAccount();
        if (! $maintenanceAcc) {
            $this->error('تعذّر إيجاد حساب إيرادات الصيانة. تأكد من وجود حساب باسم «إيرادات الصيانة» أو detail_type=maintenance_revenue.');

            return self::FAILURE;
        }

        $this->info("حساب إيرادات الصيانة: [{$maintenanceAcc->code}] {$maintenanceAcc->name}");

        $query = Order::query()
            ->where('order_type', 'طلب صيانة')
            ->whereNotIn('order_status', ['ملغي', 'أرشيف']);

        $orderId = $this->option('order');
        if ($orderId) {
            $query->where('id', (int) $orderId);
        } else {
            if ($from = $this->option('from')) {
                $query->whereDate('order_date', '>=', $from);
            }
            if ($to = $this->option('to')) {
                $query->whereDate('order_date', '<=', $to);
            }
        }

        $limit = max(1, (int) $this->option('limit'));
        $total = (clone $query)->count();
        $this->info("طلبات الصيانة المطابقة: {$total}");

        if ($total > $limit) {
            $this->error("العدد ({$total}) يتجاوز الحد ({$limit}). قلّل نطاق التاريخ أو زد --limit.");

            return self::FAILURE;
        }

        if ($this->option('dry-run') || $total === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(100, function ($orders) use ($salesAccounting, &$processed, &$failed) {
            foreach ($orders as $order) {
                try {
                    $fresh = Order::query()
                        ->with(['order_products', 'order_details.shipping_company', 'order_details.collection_company'])
                        ->find($order->id);
                    if (! $fresh || $salesAccounting->shouldSkipInvoiceRecognition($fresh)) {
                        continue;
                    }
                    $salesAccounting->refreshOrderRecognition($fresh, false);
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("طلب {$order->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("أُعيد بناء قيود الفاتورة لـ {$processed} طلب.");
        if ($failed > 0) {
            $this->warn("فشل {$failed} طلب.");
        }

        $accounting->updateAccountHierarchyBalances((int) $maintenanceAcc->id);
        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        if ($salesAcc) {
            $accounting->updateAccountHierarchyBalances((int) $salesAcc->id);
        }

        $this->info('تم تحديث أرصدة حسابات الإيراد.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
