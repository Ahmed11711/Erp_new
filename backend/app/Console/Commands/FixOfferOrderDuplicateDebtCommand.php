<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Services\Accounting\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * إصلاح ازدواج المديونية لطلبات عروض الأسعار.
 *
 * الطلبات المحوّلة من عروض أسعار رُحّلت مديونيتها وإيرادها على العرض (دفعة OFFER-*)،
 * لكن مراقب الطلب كان يُسجّل قيد فاتورة إضافي (ORD-*) يضاعف ذمة العميل والإيراد.
 * هذا الأمر يحذف قيود ORD-* لتلك الطلبات ويعيد بناء أرصدة الشجرة.
 *
 * php artisan offers:fix-duplicate-debt [--dry-run] [--order=ID]
 */
class FixOfferOrderDuplicateDebtCommand extends Command
{
    protected $signature = 'offers:fix-duplicate-debt
        {--dry-run : عرض دون تعديل}
        {--order= : معالجة طلب واحد فقط برقمه}';

    protected $description = 'حذف قيود ORD-* المكررة لطلبات عروض الأسعار وإعادة بناء أرصدة الشجرة';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Order::query()
            ->whereNotNull('offer_id')
            ->where('offer_debt_posted', 1);

        if ($this->option('order')) {
            $query->where('id', (int) $this->option('order'));
        }

        $orders = $query->orderBy('id')->get(['id', 'offer_id', 'net_total']);

        if ($orders->isEmpty()) {
            $this->info('لا توجد طلبات عروض أسعار للمعالجة.');

            return self::SUCCESS;
        }

        $accounting = app(AccountingService::class);
        $affectedAccountIds = [];
        $fixedOrders = 0;
        $deletedEntries = 0;

        foreach ($orders as $order) {
            $pattern = 'ORD-' . $order->id . '-%';

            $entries = AccountEntry::query()
                ->where('order_id', $order->id)
                ->where('entry_batch_code', 'like', $pattern)
                ->get(['id', 'tree_account_id', 'debit', 'credit']);

            if ($entries->isEmpty()) {
                continue;
            }

            $fixedOrders++;
            $deletedEntries += $entries->count();

            $this->line(sprintf(
                '#%d (عرض %s): سيُحذف %d قيد ORD-* بإجمالي مدين %s',
                $order->id,
                (string) $order->offer_id,
                $entries->count(),
                number_format((float) $entries->sum('debit'), 2)
            ));

            foreach ($entries as $e) {
                $affectedAccountIds[(int) $e->tree_account_id] = true;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($order, $pattern) {
                    AccountEntry::query()
                        ->where('order_id', $order->id)
                        ->where('entry_batch_code', 'like', $pattern)
                        ->delete();
                });
            }
        }

        if ($fixedOrders === 0) {
            $this->info('كل الطلبات سليمة — لا توجد قيود ORD-* مكررة.');

            return self::SUCCESS;
        }

        if (! $dryRun) {
            foreach (array_keys($affectedAccountIds) as $accountId) {
                try {
                    $accounting->updateAccountHierarchyBalances((int) $accountId);
                } catch (\Throwable $e) {
                    $this->warn('تعذر إعادة بناء رصيد الحساب ' . $accountId . ': ' . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%sتمت معالجة %d طلب وحذف %d قيد ORD-* مكرر وإعادة بناء %d حساب.',
            $dryRun ? '[dry-run] ' : '',
            $fixedOrders,
            $deletedEntries,
            count($affectedAccountIds)
        ));

        return self::SUCCESS;
    }
}
