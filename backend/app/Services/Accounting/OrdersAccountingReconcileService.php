<?php

namespace App\Services\Accounting;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * إعادة بناء قيود الفاتورة المعتمدة على الطلب (ORD-* واختياريًا ORD-PREPAID-*)
 * ضمن نطاق تاريخ الطلب، ثم إعادة حساب أرصدة شجرة الحسابات.
 *
 * لا يُمس قيود التحصيل (COLLECT-* / PARTCOLLECT-* / PREPAID-REV-* ...) المسجَّلة عند تحصيل فعلي.
 */
class OrdersAccountingReconcileService
{
    public const MAX_ORDERS_PER_RUN = 2000;

    private const LOCK_KEY = 'orders_accounting_reconcile_lock';
    private const LOCK_TTL_SECONDS = 600;

    private const EXCLUDED_STATUSES = ['ملغي', 'أرشيف'];

    private const ACTIVE_STATUSES = [
        'طلب جديد', 'طلب مؤكد', 'شحن جزئي',
        'تم شحن', 'تم التسليم', 'تم الاستلام', 'تم التحصيل',
        'مؤجل', 'رفض استلام', 'تم الصيانة',
    ];

    public function __construct(
        private SalesOrderAccountingService $salesOrderAccounting,
        private AccountingService $accountingService
    ) {
    }

    /**
     * @return array{total: int, active: int, excluded: int, by_status: array<string, int>}
     */
    public function countInRange(string $dateFrom, string $dateTo): array
    {
        $base = $this->rawDateQuery($dateFrom, $dateTo);

        $total = (clone $base)->count();
        $active = (clone $base)->whereIn('order_status', self::ACTIVE_STATUSES)->count();
        $excluded = (clone $base)->whereIn('order_status', self::EXCLUDED_STATUSES)->count();

        $byStatus = (clone $base)
            ->selectRaw('order_status, count(*) as cnt')
            ->groupBy('order_status')
            ->pluck('cnt', 'order_status')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'excluded' => $excluded,
            'by_status' => $byStatus,
        ];
    }

    /**
     * @return array{success: bool, message: string, orders_processed?: int, orders_failed?: int, orders_skipped_cancelled?: int, failures?: list<array{order_id: int, error: string}>|array{}, hierarchy_recalc?: array<string, mixed>}
     */
    public function reconcileRange(
        string $dateFrom,
        string $dateTo,
        bool $rebuildPrepaid = false,
        ?int $maxOrders = null
    ): array {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);
        if (!$lock->get()) {
            return [
                'success' => false,
                'message' => 'عملية تسوية أخرى قيد التنفيذ. الرجاء الانتظار حتى تنتهي.',
            ];
        }

        try {
            return $this->doReconcile($dateFrom, $dateTo, $rebuildPrepaid, $maxOrders);
        } finally {
            $lock->release();
        }
    }

    private function doReconcile(
        string $dateFrom,
        string $dateTo,
        bool $rebuildPrepaid,
        ?int $maxOrders
    ): array {
        $limit = $maxOrders ?? self::MAX_ORDERS_PER_RUN;

        $query = $this->baseQuery($dateFrom, $dateTo)->orderBy('id');
        $total = (clone $query)->count();

        if ($total > $limit) {
            return [
                'success' => false,
                'message' => "عدد الطلبات النشطة في النطاق ({$total}) يتجاوز الحد المسموح ({$limit}). قلّل نطاق التاريخ أو نفّذ على دفعات.",
            ];
        }

        $skippedCancelled = $this->rawDateQuery($dateFrom, $dateTo)
            ->whereIn('order_status', self::EXCLUDED_STATUSES)
            ->count();

        $processed = 0;
        $failures = [];

        $query->chunkById(100, function ($orders) use (&$processed, &$failures, $rebuildPrepaid) {
            foreach ($orders as $order) {
                try {
                    $fresh = Order::query()
                        ->with([
                            'order_products',
                            'order_details.shipping_company',
                            'order_details.collection_company',
                        ])
                        ->find($order->id);
                    if (!$fresh) {
                        continue;
                    }
                    $this->salesOrderAccounting->refreshOrderRecognition($fresh, $rebuildPrepaid);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::warning('OrdersAccountingReconcile: order reconcile failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failures[] = [
                        'order_id' => (int) $order->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        $hierarchy = $this->accountingService->recalculateAllHierarchyBalances();

        $msg = $processed > 0
            ? "تمت إعادة بناء قيود الفاتورة لـ {$processed} طلب وتحديث أرصدة الشجرة."
            : 'لم تُعالَج أي طلبات بنجاح.';

        if ($skippedCancelled > 0) {
            $msg .= " تم تجاوز {$skippedCancelled} طلب ملغي/مؤرشف.";
        }

        if (count($failures) > 0) {
            $msg .= ' راجع قائمة الطلبات الفاشلة في الاستجابة.';
        }

        return [
            'success' => true,
            'message' => $msg,
            'orders_processed' => $processed,
            'orders_failed' => count($failures),
            'orders_skipped_cancelled' => $skippedCancelled,
            'failures' => $failures,
            'hierarchy_recalc' => $hierarchy,
        ];
    }

    private function baseQuery(string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        return $this->rawDateQuery($dateFrom, $dateTo)
            ->whereNotIn('order_status', self::EXCLUDED_STATUSES);
    }

    private function rawDateQuery(string $dateFrom, string $dateTo): \Illuminate\Database\Eloquent\Builder
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        return Order::query()
            ->whereDate('order_date', '>=', $from->format('Y-m-d'))
            ->whereDate('order_date', '<=', $to->format('Y-m-d'));
    }

    public static function maxRangeDaysExceeded(string $dateFrom, string $dateTo, int $maxDays): bool
    {
        return Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > $maxDays;
    }
}
