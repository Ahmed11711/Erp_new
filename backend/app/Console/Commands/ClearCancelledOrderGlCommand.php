<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Services\Orders\OrderCancellationAccountingService;
use Illuminate\Console\Command;

/**
 * طلبات ملغاة بقي عليها قيد فاتورة في الشجرة (كان المراقب يعيد إنشاء ORD-* بعد المسح).
 *
 * php artisan orders:clear-cancelled-gl [--dry-run] [--order=44362]
 */
class ClearCancelledOrderGlCommand extends Command
{
    protected $signature = 'orders:clear-cancelled-gl
        {--dry-run : عرض دون تعديل}
        {--order= : معالجة طلب واحد فقط برقمه}';

    protected $description = 'حذف قيود الشجرة المتبقية لطلبات ملغاة/مؤرشفة وإعادة تصفير الذمة';

    public function handle(OrderCancellationAccountingService $cancelAccounting): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orderId = $this->option('order') ? (int) $this->option('order') : null;

        $orderIds = AccountEntry::query()
            ->select('account_entries.order_id')
            ->join('orders', 'orders.id', '=', 'account_entries.order_id')
            ->whereIn('orders.order_status', ['ملغي', 'أرشيف'])
            ->when($orderId, fn ($q) => $q->where('orders.id', $orderId))
            ->groupBy('account_entries.order_id')
            ->pluck('account_entries.order_id');

        if ($orderIds->isEmpty()) {
            $this->info($orderId
                ? "الطلب {$orderId} لا يملك قيوداً متبقية أو حالته ليست ملغي/أرشيف."
                : 'لا توجد طلبات ملغاة/مؤرشفة عليها قيود في الشجرة.');

            return self::SUCCESS;
        }

        $userId = (int) (config('services.shopify.tracking_user_id') ?: 1);
        $fixed = 0;
        $deleted = 0;

        foreach ($orderIds as $id) {
            $count = AccountEntry::query()->where('order_id', $id)->count();
            $debit = (float) AccountEntry::query()->where('order_id', $id)->sum('debit');
            $this->line(sprintf(
                '#%d: %d قيد — مدين %s',
                $id,
                $count,
                number_format($debit, 2)
            ));

            $fixed++;
            $deleted += $count;

            if ($dryRun) {
                continue;
            }

            $order = Order::with('order_details')->find($id);
            if (! $order) {
                continue;
            }

            $cancelAccounting->handleCancellation($order, $userId);
        }

        $this->newLine();
        $this->info(sprintf(
            '%sتمت معالجة %d طلب وحذف %d قيد.',
            $dryRun ? '[dry-run] ' : '',
            $fixed,
            $deleted
        ));

        return self::SUCCESS;
    }
}
