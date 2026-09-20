<?php

namespace App\Services\Accounting;

use App\Models\CollectionCompany;
use App\Models\Order;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * إعادة حساب مديونيات شركات الشحن/المناديب وشركات التحصيل
 * من واقع الطلبات ضمن فترة معيّنة، وتحديث أرصدة حسابات الشجرة.
 */
class ReceivableReconciliationService
{
    private const LOCK_KEY = 'receivable_reconciliation_lock';
    private const LOCK_TTL = 600;
    private const MAX_ORDERS = 5000;

    private const EXCLUDED_STATUSES = ['ملغي', 'أرشيف'];

    public function __construct(
        private SalesOrderAccountingService $salesAccounting,
        private AccountingService $accountingService,
        private TreeAccountBalanceRebuildService $treeRebuild
    ) {
    }

    /**
     * عدّ الطلبات المرتبطة بشركة شحن/مندوب في الفترة.
     */
    public function countShippingOrders(int $companyId, string $dateFrom, string $dateTo): array
    {
        $base = $this->shippingOrdersQuery($companyId, $dateFrom, $dateTo);
        $total = (clone $base)->count();
        $active = (clone $base)->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES)->count();

        return [
            'total' => $total,
            'active' => $active,
            'excluded' => $total - $active,
        ];
    }

    /**
     * عدّ الطلبات المرتبطة بشركة تحصيل في الفترة.
     */
    public function countCollectionOrders(int $companyId, string $dateFrom, string $dateTo): array
    {
        $base = $this->collectionOrdersQuery($companyId, $dateFrom, $dateTo);
        $total = (clone $base)->count();
        $active = (clone $base)->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES)->count();

        return [
            'total' => $total,
            'active' => $active,
            'excluded' => $total - $active,
        ];
    }

    /**
     * إعادة حساب مديونيات شركة شحن/مندوب.
     */
    public function reconcileShippingCompany(
        int $companyId,
        string $dateFrom,
        string $dateTo,
        ?array $orderIds = null
    ): array {
        $lock = Cache::lock(self::LOCK_KEY . '_shipping_' . $companyId, self::LOCK_TTL);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'عملية أخرى قيد التنفيذ لهذه الشركة. الرجاء الانتظار.'];
        }

        try {
            return $this->doReconcileShipping($companyId, $dateFrom, $dateTo, $orderIds);
        } finally {
            $lock->release();
        }
    }

    /**
     * إعادة حساب مديونيات شركة تحصيل.
     */
    public function reconcileCollectionCompany(
        int $companyId,
        string $dateFrom,
        string $dateTo,
        ?array $orderIds = null
    ): array {
        $lock = Cache::lock(self::LOCK_KEY . '_collection_' . $companyId, self::LOCK_TTL);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'عملية أخرى قيد التنفيذ لهذه الشركة. الرجاء الانتظار.'];
        }

        try {
            return $this->doReconcileCollection($companyId, $dateFrom, $dateTo, $orderIds);
        } finally {
            $lock->release();
        }
    }

    private function doReconcileShipping(int $companyId, string $dateFrom, string $dateTo, ?array $orderIds): array
    {
        $company = ShippingCompany::find($companyId);
        if (! $company) {
            return ['success' => false, 'message' => 'الشركة/المندوب غير موجود.'];
        }

        if (! $company->receivable_tree_account_id) {
            return ['success' => false, 'message' => 'الشركة/المندوب غير مربوط بحساب في شجرة الحسابات.'];
        }

        $query = $this->shippingOrdersQuery($companyId, $dateFrom, $dateTo)
            ->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES);

        if ($orderIds !== null && count($orderIds) > 0) {
            $query->whereIn('orders.id', $orderIds);
        }

        $total = (clone $query)->count();
        if ($total > self::MAX_ORDERS) {
            return [
                'success' => false,
                'message' => "عدد الطلبات ({$total}) يتجاوز الحد المسموح (" . self::MAX_ORDERS . '). قلّل الفترة أو اختر طلبات محددة.',
            ];
        }

        return $this->processOrders($query, (int) $company->receivable_tree_account_id, $company->name);
    }

    private function doReconcileCollection(int $companyId, string $dateFrom, string $dateTo, ?array $orderIds): array
    {
        $company = CollectionCompany::find($companyId);
        if (! $company) {
            return ['success' => false, 'message' => 'شركة التحصيل غير موجودة.'];
        }

        if (! $company->receivable_tree_account_id) {
            return ['success' => false, 'message' => 'شركة التحصيل غير مربوطة بحساب في شجرة الحسابات.'];
        }

        $query = $this->collectionOrdersQuery($companyId, $dateFrom, $dateTo)
            ->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES);

        if ($orderIds !== null && count($orderIds) > 0) {
            $query->whereIn('orders.id', $orderIds);
        }

        $total = (clone $query)->count();
        if ($total > self::MAX_ORDERS) {
            return [
                'success' => false,
                'message' => "عدد الطلبات ({$total}) يتجاوز الحد المسموح (" . self::MAX_ORDERS . '). قلّل الفترة أو اختر طلبات محددة.',
            ];
        }

        return $this->processOrders($query, (int) $company->receivable_tree_account_id, $company->name);
    }

    private function processOrders($query, int $treeAccountId, string $entityName): array
    {
        $processed = 0;
        $failures = [];

        $orderIds = $query->distinct()->pluck('orders.id')->all();

        foreach (array_chunk($orderIds, 100) as $chunk) {
            foreach ($chunk as $orderId) {
                try {
                    $fresh = Order::query()
                        ->with([
                            'order_products',
                            'order_details.shipping_company',
                            'order_details.collection_company',
                        ])
                        ->find($orderId);

                    if (! $fresh) {
                        continue;
                    }

                    $this->salesAccounting->refreshOrderRecognition($fresh, true);
                    $processed++;
                } catch (\Throwable $e) {
                    Log::warning('ReceivableReconciliation: order reconcile failed', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                    $failures[] = [
                        'order_id' => (int) $orderId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        $this->treeRebuild->rebuildAncestorChain($treeAccountId);

        $newBalance = TreeAccount::find($treeAccountId)?->balance ?? 0;

        $msg = $processed > 0
            ? "تمت إعادة حساب مديونيات «{$entityName}» لعدد {$processed} طلب."
            : 'لم تُعالَج أي طلبات.';

        if (count($failures) > 0) {
            $msg .= ' فشل ' . count($failures) . ' طلب. راجع التفاصيل.';
        }

        return [
            'success' => true,
            'message' => $msg,
            'orders_processed' => $processed,
            'orders_failed' => count($failures),
            'failures' => $failures,
            'new_balance' => round((float) $newBalance, 2),
        ];
    }

    /**
     * الطلبات المرتبطة بشركة شحن/مندوب عبر order_details.
     */
    private function shippingOrdersQuery(int $companyId, string $dateFrom, string $dateTo)
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        return Order::query()
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->where('order_details.shipping_company_id', $companyId)
            ->whereDate('orders.order_date', '>=', $from->format('Y-m-d'))
            ->whereDate('orders.order_date', '<=', $to->format('Y-m-d'));
    }

    /**
     * الطلبات المرتبطة بشركة تحصيل عبر order_details.
     */
    private function collectionOrdersQuery(int $companyId, string $dateFrom, string $dateTo)
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        return Order::query()
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->where(function ($q) use ($companyId) {
                $q->where('order_details.collection_company_id', $companyId)
                  ->orWhere('order_details.collection_provider_id', $companyId);
            })
            ->whereDate('orders.order_date', '>=', $from->format('Y-m-d'))
            ->whereDate('orders.order_date', '<=', $to->format('Y-m-d'));
    }

    /**
     * جلب طلبات شركة شحن/مندوب ضمن الفترة (للاختيار في الواجهة).
     */
    public function listShippingOrders(int $companyId, string $dateFrom, string $dateTo): array
    {
        return $this->shippingOrdersQuery($companyId, $dateFrom, $dateTo)
            ->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES)
            ->select([
                'orders.id',
                'orders.order_date',
                'orders.customer_name',
                'orders.net_total',
                'orders.order_status',
            ])
            ->orderBy('orders.order_date', 'desc')
            ->limit(2000)
            ->get()
            ->toArray();
    }

    /**
     * جلب طلبات شركة تحصيل ضمن الفترة.
     */
    public function listCollectionOrders(int $companyId, string $dateFrom, string $dateTo): array
    {
        return $this->collectionOrdersQuery($companyId, $dateFrom, $dateTo)
            ->whereNotIn('orders.order_status', self::EXCLUDED_STATUSES)
            ->select([
                'orders.id',
                'orders.order_date',
                'orders.customer_name',
                'orders.net_total',
                'orders.order_status',
            ])
            ->orderBy('orders.order_date', 'desc')
            ->limit(2000)
            ->get()
            ->toArray();
    }
}
