<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStatusVisibilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ترحيل القيود الناقصة للطلبات المشحونة: إثبات المبيعات (ORD-*) وتكلفة خروج المخزن (COGS-*).
 *
 * لا يحذف قيوداً، ولا يعيد بناء قيود موجودة، ولا ينقص كمية المخزن مرة أخرى.
 * التسليم وتسوية الرفض يُرحَّلان حسب الحالة عبر دورة حياة الطلب.
 */
class MissingSalesInvoiceJournalService
{
    public const MAX_ORDERS_PER_RUN = 2000;

    public const MODE_ORDER_DATE = 'order_date';

    public const MODE_SHIPPING_DATE = 'shipping_date';

    private const LOCK_TTL_SECONDS = 600;

    private const EXCLUDED_STATUSES = ['ملغي', 'أرشيف'];

    public function __construct(
        private SalesOrderAccountingService $salesOrderAccounting,
        private OrderStatusVisibilityService $statusVisibility,
        private SalesOrderLifecycleJournalService $lifecycle,
        private SalesOrderCogsJournalService $cogsJournal,
        private SalesOrderCollectionJournalService $collectionJournal,
    ) {
    }

    /**
     * @return array{
     *     missing_count: int,
     *     missing_invoice_count: int,
     *     missing_cogs_count: int,
     *     already_posted_count: int,
     *     skipped_ineligible_count: int,
     *     sample_order_ids: list<int>,
     *     date_from: string,
     *     date_to: string,
     *     mode: string,
     *     max_orders_per_run: int,
     *     would_exceed_limit: bool
     * }
     */
    public function preview(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): array
    {
        $mode = self::normalizeMode($mode);
        $missingInvoiceIds = $this->missingInvoiceOrderIds($user, $dateFrom, $dateTo, $mode);
        $missingCogsIds = $this->missingCogsOrderIds($user, $dateFrom, $dateTo, $mode);
        $missingIds = $this->unionIds($missingInvoiceIds, $missingCogsIds);
        $eligibleCount = $this->eligibleQuery($user, $dateFrom, $dateTo, $mode)->count();
        $ineligibleCount = $this->rawDateQuery($user, $dateFrom, $dateTo, $mode)
            ->where(function ($q) {
                $q->whereIn('order_status', self::EXCLUDED_STATUSES)
                    ->orWhere('offer_debt_posted', 1);
            })
            ->count();

        $missingCount = count($missingIds);
        $missingInvoiceCount = count($missingInvoiceIds);

        return [
            'missing_count' => $missingCount,
            'missing_invoice_count' => $missingInvoiceCount,
            'missing_cogs_count' => count($missingCogsIds),
            'already_posted_count' => max(0, $eligibleCount - $missingInvoiceCount),
            'skipped_ineligible_count' => $ineligibleCount,
            'sample_order_ids' => array_slice($missingIds, 0, 15),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'max_orders_per_run' => self::MAX_ORDERS_PER_RUN,
            'would_exceed_limit' => $missingCount > self::MAX_ORDERS_PER_RUN,
        ];
    }

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     orders_posted?: int,
     *     orders_skipped_exists?: int,
     *     orders_skipped_ineligible?: int,
     *     orders_failed?: int,
     *     failures?: list<array{order_id: int, error: string}>
     * }
     */
    public function postMissing(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): array
    {
        $lock = Cache::lock(OrdersAccountingReconcileService::LOCK_KEY, self::LOCK_TTL_SECONDS);
        if (! $lock->get()) {
            return [
                'success' => false,
                'message' => 'عملية ترحيل أو تسوية قيود أخرى قيد التنفيذ. الرجاء الانتظار حتى تنتهي.',
            ];
        }

        try {
            return $this->doPostMissing($user, $dateFrom, $dateTo, self::normalizeMode($mode));
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function previewOrder(User $user, int $orderId): array
    {
        $order = $this->findVisibleOrder($user, $orderId);
        if (! $order) {
            return [
                'order_id' => $orderId,
                'can_post' => false,
                'reason' => 'الطلب غير موجود أو غير مصرح بعرضه.',
                'lines' => [],
                'total_debit' => 0,
                'total_credit' => 0,
            ];
        }

        $preview = $this->salesOrderAccounting->previewInvoiceRecognition($order);
        $cogs = $this->cogsJournal->preview($order);
        $prepaid = $this->salesOrderAccounting->previewPrepaid($order);
        $delivery = $this->lifecycle->previewDelivery($order);
        $collection = $this->collectionJournal->previewCollection($order);
        $shippingExpense = $this->collectionJournal->previewShippingExpense($order);
        $journals = [
            $this->mapJournalDraft('invoice', 'إثبات المبيعات — تاريخ الشحن', $preview),
        ];
        if (($cogs['applicable'] ?? false) || ($cogs['posted'] ?? false) || ($cogs['can_post'] ?? false)) {
            $journals[] = $this->mapJournalDraft('cogs', 'تكلفة خروج المخزن التام', $cogs);
        }
        if (($prepaid['applicable'] ?? false) || ($prepaid['posted'] ?? false) || ($prepaid['can_post'] ?? false)) {
            $journals[] = $this->mapJournalDraft('prepaid', 'تاريخ السداد المقدم', $prepaid);
        }
        if (($delivery['applicable'] ?? false) || ($delivery['posted'] ?? false) || ($delivery['can_post'] ?? false)) {
            $journals[] = $this->mapJournalDraft('delivery', 'تاريخ التسليم — نقل الذمة', $delivery);
        }
        if (($collection['applicable'] ?? false) || ($collection['posted'] ?? false) || ($collection['can_post'] ?? false)) {
            $journals[] = $this->mapJournalDraft('collection', 'تاريخ التحصيل — تحصيل الذمة نقداً', $collection);
        }
        if (($shippingExpense['applicable'] ?? false) || ($shippingExpense['posted'] ?? false) || ($shippingExpense['can_post'] ?? false)) {
            $journals[] = $this->mapJournalDraft('shipping_expense', 'تاريخ التحصيل — تسوية مصروف الشحن', $shippingExpense);
        }
        $canPostAny = $preview['can_post']
            || $cogs['can_post']
            || $prepaid['can_post']
            || $delivery['can_post']
            || $collection['can_post']
            || $shippingExpense['can_post'];

        $preview['cogs'] = $cogs;
        $preview['prepaid'] = $prepaid;
        $preview['delivery'] = $delivery;
        $preview['collection'] = $collection;
        $preview['shipping_expense'] = $shippingExpense;
        $preview['journals'] = $journals;
        $preview['can_post'] = $canPostAny;
        if ($canPostAny) {
            $preview['reason'] = null;
        } elseif (($delivery['applicable'] ?? false) && ! ($delivery['posted'] ?? false) && ! empty($delivery['reason'])) {
            $preview['reason'] = $delivery['reason'];
        } elseif (($collection['applicable'] ?? false) && ! ($collection['posted'] ?? false) && ! empty($collection['reason'])) {
            $preview['reason'] = $collection['reason'];
        } elseif (
            str_contains((string) ($preview['reason'] ?? ''), 'موجود مسبقاً')
            && str_contains((string) ($cogs['reason'] ?? ''), 'بدون تكلفة أصناف')
        ) {
            $preview['reason'] = 'لا توجد قيود ناقصة للترحيل. إثبات المبيعات موجود، وإيراد الشحن داخل هذا القيد — لا يُترحَّل خروج مخزن لطلب بدون تكلفة أصناف.';
        } elseif (($cogs['applicable'] ?? false) && ! ($cogs['posted'] ?? false) && ! empty($cogs['reason'])) {
            $preview['reason'] = $cogs['reason'];
        } elseif (! $preview['reason']) {
            $preview['reason'] = $prepaid['reason'] ?? $cogs['reason'] ?? $delivery['reason'] ?? null;
        }

        return $preview;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>|null  $lines
     * @return array<string, mixed>
     */
    public function postOrder(
        User $user,
        int $orderId,
        ?array $lines = null,
        ?string $date = null,
        ?string $description = null,
        ?array $journals = null
    ): array {
        $lock = Cache::lock(OrdersAccountingReconcileService::LOCK_KEY, self::LOCK_TTL_SECONDS);
        if (! $lock->get()) {
            return [
                'success' => false,
                'status' => 'locked',
                'message' => 'عملية ترحيل أو تسوية قيود أخرى قيد التنفيذ. الرجاء الانتظار حتى تنتهي.',
            ];
        }

        try {
            $order = $this->findVisibleOrder($user, $orderId);
            if (! $order) {
                return [
                    'success' => false,
                    'status' => 'not_found',
                    'message' => 'الطلب غير موجود أو غير مصرح بعرضه.',
                ];
            }

            if (is_array($journals) && $journals !== []) {
                return $this->postSelectedJournals($order, $journals);
            }

            $journalDate = null;
            if ($date) {
                try {
                    $journalDate = Carbon::parse($date)->startOfDay();
                } catch (\Throwable $e) {
                    $journalDate = null;
                }
            }

            if (is_array($lines) && $lines !== []) {
                $result = $this->salesOrderAccounting->recordInvoiceRecognitionWithLines(
                    $order,
                    $lines,
                    $description,
                    $journalDate
                );
            } else {
                $result = $this->salesOrderAccounting->recordInitialOrderRecognitionIfMissing($order);
            }

            $fresh = $order->fresh(['order_products', 'order_details.shipping_company', 'order_details.collection_company']) ?? $order;
            $lifecycle = $this->lifecycle->postMissingForOrder($fresh);
            $lifecycle = $this->mergeCollectionLifecycle($lifecycle, $this->collectionJournal->postMissingForOrder($fresh));

            $lifecyclePosted = $lifecycle['posted'] ?? [];
            $hasCustomLines = is_array($lines) && $lines !== [];
            $success = $result === 'posted' || $lifecyclePosted !== [];
            $status = $success ? 'posted' : $result;
            $message = $this->postOrderMessage($order, $result, $lifecyclePosted);

            if (! $success && ! $hasCustomLines && ($lifecycle['prepaid'] ?? null) === 'failed') {
                $status = 'failed';
                $message = 'تعذر ترحيل قيد السداد المقدم للطلب #'.$order->id.'. البنك المرتبط غير موجود أو حساب المقبوضات المعلّقة غير مهيأ في الشجرة.';
            }

            return [
                'success' => $success,
                'status' => $status,
                'message' => $message,
                'order_id' => (int) $order->id,
                'lifecycle' => $lifecycle,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function doPostMissing(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): array
    {
        $missingIds = $this->unionIds(
            $this->missingInvoiceOrderIds($user, $dateFrom, $dateTo, $mode),
            $this->missingCogsOrderIds($user, $dateFrom, $dateTo, $mode)
        );
        $total = count($missingIds);

        if ($total > self::MAX_ORDERS_PER_RUN) {
            return [
                'success' => false,
                'message' => "عدد الطلبات الناقصة ({$total}) يتجاوز الحد المسموح (" . self::MAX_ORDERS_PER_RUN . '). قلّل نطاق التاريخ.',
            ];
        }

        $posted = 0;
        $skippedExists = 0;
        $skippedIneligible = 0;
        $failures = [];

        foreach (array_chunk($missingIds, 100) as $chunk) {
            $orders = Order::query()
                ->with([
                    'order_products',
                    'order_details.shipping_company',
                    'order_details.collection_company',
                ])
                ->whereIn('id', $chunk)
                ->get()
                ->keyBy('id');

            foreach ($chunk as $orderId) {
                $order = $orders->get($orderId);
                if (! $order) {
                    continue;
                }

                try {
                    $result = $this->salesOrderAccounting->recordInitialOrderRecognitionIfMissing($order);
                    $lifecycle = $this->lifecycle->postMissingForOrder($order);
                    $lifecycle = $this->mergeCollectionLifecycle($lifecycle, $this->collectionJournal->postMissingForOrder($order));
                    if ($result === 'posted' || ($lifecycle['posted'] ?? []) !== []) {
                        $posted++;
                    } elseif ($result === 'skipped_exists') {
                        $skippedExists++;
                    } elseif (in_array($result, ['skipped_ineligible', 'skipped_not_shipped'], true)) {
                        $skippedIneligible++;
                    } else {
                        $failures[] = [
                            'order_id' => (int) $order->id,
                            'error' => 'تعذر إنشاء قيد الفاتورة (حساب العميل أو الإيرادات غير متوفر، أو صافي الفاتورة صفر).',
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('MissingSalesInvoiceJournal: post failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failures[] = [
                        'order_id' => (int) $order->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        $failed = count($failures);
        $msg = $posted > 0
            ? "تم ترحيل القيود الناقصة (فاتورة و/أو تكلفة المخزن) لـ {$posted} طلب."
            : 'لم يُرحَّل أي قيد جديد.';

        if ($skippedExists > 0) {
            $msg .= " تُرك {$skippedExists} طلب لأن القيد موجود مسبقاً.";
        }
        if ($skippedIneligible > 0) {
            $msg .= " تم تجاوز {$skippedIneligible} طلب ملغي/مؤرشف أو محوّل من عرض.";
        }
        if ($failed > 0) {
            $msg .= ' راجع قائمة الطلبات الفاشلة في الاستجابة.';
        }

        return [
            'success' => true,
            'message' => $msg,
            'orders_posted' => $posted,
            'orders_skipped_exists' => $skippedExists,
            'orders_skipped_ineligible' => $skippedIneligible,
            'orders_failed' => $failed,
            'failures' => $failures,
        ];
    }

    /**
     * @return list<int>
     */
    private function missingInvoiceOrderIds(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): array
    {
        $eligibleIds = $this->eligibleQuery($user, $dateFrom, $dateTo, $mode)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($eligibleIds === []) {
            return [];
        }

        $postedIds = AccountEntry::query()
            ->whereIn('order_id', $eligibleIds)
            ->whereRaw("entry_batch_code LIKE CONCAT('ORD-', order_id, '-%')")
            ->distinct()
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->idsMissingFromSet($eligibleIds, $postedIds);
    }

    /**
     * @return list<int>
     */
    private function missingCogsOrderIds(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): array
    {
        $eligibleIds = $this->cogsEligibleQuery($user, $dateFrom, $dateTo, $mode)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($eligibleIds === []) {
            return [];
        }

        $postedSet = $this->cogsJournal->postedCogsOrderIdSet($eligibleIds);

        return $this->idsMissingFromSet($eligibleIds, array_keys($postedSet));
    }

    /**
     * @param  list<int>  $eligibleIds
     * @param  list<int>  $postedIds
     * @return list<int>
     */
    private function idsMissingFromSet(array $eligibleIds, array $postedIds): array
    {
        $postedSet = array_fill_keys($postedIds, true);
        $missing = [];
        foreach ($eligibleIds as $id) {
            if (! isset($postedSet[$id])) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    /**
     * @param  list<int>  $left
     * @param  list<int>  $right
     * @return list<int>
     */
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function mapJournalDraft(string $key, string $title, array $draft): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'applicable' => array_key_exists('applicable', $draft)
                ? (bool) $draft['applicable']
                : true,
            'posted' => (bool) ($draft['posted'] ?? (
                ! ($draft['can_post'] ?? false)
                && str_contains((string) ($draft['reason'] ?? ''), 'موجود مسبقاً')
            )),
            'can_post' => (bool) ($draft['can_post'] ?? false),
            'reason' => $draft['reason'] ?? null,
            'date' => $draft['date'] ?? null,
            'description' => $draft['description'] ?? $title,
            'total' => round((float) ($draft['total'] ?? $draft['total_debit'] ?? 0), 2),
            'lines' => is_array($draft['lines'] ?? null) ? $draft['lines'] : [],
            'sync_operational_default' => (bool) ($draft['sync_operational_default'] ?? false),
            'cash_source' => $draft['cash_source'] ?? null,
            'cash_source_already_recorded' => (bool) ($draft['cash_source_already_recorded'] ?? false),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     * @return array<string, mixed>
     */
    private function postSelectedJournals(Order $order, array $journals): array
    {
        $posted = [];
        $fresh = $order->fresh(['order_products', 'order_details.shipping_company', 'order_details.collection_company']) ?? $order;

        foreach ($journals as $journal) {
            $key = (string) ($journal['key'] ?? '');
            $lines = is_array($journal['lines'] ?? null) ? $journal['lines'] : [];
            if ($key === '' || $lines === []) {
                continue;
            }
            $date = null;
            if (! empty($journal['date'])) {
                try {
                    $date = Carbon::parse((string) $journal['date'])->startOfDay();
                } catch (\Throwable $e) {
                    $date = null;
                }
            }
            $description = isset($journal['description']) ? (string) $journal['description'] : null;
            $syncOperational = array_key_exists('sync_operational', $journal)
                ? (bool) $journal['sync_operational']
                : null;
            $status = match ($key) {
                'invoice' => $this->salesOrderAccounting->recordInvoiceRecognitionWithLines($fresh, $lines, $description, $date),
                'cogs' => $this->cogsJournal->postIfMissingWithLines($fresh, $lines, $description, $date),
                'prepaid' => $this->salesOrderAccounting->recordPrepaidWithLines($fresh, $lines, $description, $date),
                'delivery' => $this->lifecycle->recordDeliveryWithLines($fresh, $lines, $description, $date),
                'collection' => $this->collectionJournal->recordCollectionWithLines($fresh, $lines, $description, $date, $syncOperational),
                'shipping_expense' => $this->collectionJournal->recordShippingExpenseWithLines($fresh, $lines, $description, $date),
                default => 'skipped_ineligible',
            };
            if ($status === 'posted') {
                $posted[] = $key;
            }
        }

        return [
            'success' => $posted !== [],
            'status' => $posted !== [] ? 'posted' : 'failed',
            'message' => $posted !== []
                ? $this->postOrderMessage($order, 'posted', $posted)
                : 'تعذر ترحيل القيود المعروضة. راجع توازن كل قيد والحسابات.',
            'order_id' => (int) $order->id,
            'lifecycle' => ['posted' => $posted, 'skipped' => []],
        ];
    }

    /**
     * @param  list<string>  $lifecyclePosted
     */
    private function postOrderMessage(Order $order, string $invoiceResult, array $lifecyclePosted): string
    {
        $labels = [
            'prepaid' => 'السداد المقدم',
            'invoice' => 'إثبات المبيعات',
            'cogs' => 'تكلفة خروج المخزن',
            'delivery' => 'نقل الذمة عند التسليم',
            'collection' => 'تحصيل الذمة نقداً',
            'shipping_expense' => 'تسوية مصروف الشحن',
            'return_sales' => 'تسوية رد البضاعة',
            'cogs_reversal' => 'عكس التكلفة',
        ];
        if ($lifecyclePosted !== []) {
            $names = [];
            foreach ($lifecyclePosted as $key) {
                $names[] = $labels[$key] ?? $key;
            }

            return 'تم ترحيل القيود الناقصة للطلب #'.$order->id.': '.implode(' و', $names).'.';
        }

        $messages = [
            'posted' => 'تم ترحيل قيد إثبات الفاتورة للطلب #'.$order->id.'.',
            'skipped_exists' => 'قيد إثبات الفاتورة موجود مسبقاً لهذا الطلب.',
            'skipped_ineligible' => 'الطلب ملغي أو مؤرشف أو محوّل من عرض سعر.',
            'skipped_not_shipped' => 'لم يُشحن الطلب بعد — قيد المبيعات يُرحَّل بتاريخ الشحن.',
            'failed' => 'تعذر ترحيل القيد. راجع الحسابات والمبالغ.',
        ];

        return $messages[$invoiceResult] ?? 'تعذر ترحيل القيد.';
    }

    /**
     * يضم نتيجة قيود التحصيل إلى نتيجة دورة حياة الطلب بنفس الشكل (posted / skipped).
     *
     * @param  array<string, mixed>  $lifecycle
     * @param  array{collection: string|null, shipping_expense: string|null, posted: list<string>}  $collection
     * @return array<string, mixed>
     */
    private function mergeCollectionLifecycle(array $lifecycle, array $collection): array
    {
        $lifecycle['collection'] = $collection['collection'] ?? null;
        $lifecycle['shipping_expense'] = $collection['shipping_expense'] ?? null;
        $lifecycle['posted'] = array_values(array_merge($lifecycle['posted'] ?? [], $collection['posted'] ?? []));

        $skipped = $lifecycle['skipped'] ?? [];
        foreach (['collection', 'shipping_expense'] as $key) {
            $status = $collection[$key] ?? null;
            if ($status !== null && $status !== 'posted') {
                $skipped[] = $key.':'.$status;
            }
        }
        $lifecycle['skipped'] = $skipped;

        return $lifecycle;
    }

    /**
     * @param  list<int>  $left
     * @param  list<int>  $right
     * @return list<int>
     */
    private function unionIds(array $left, array $right): array
    {
        $set = [];
        foreach (array_merge($left, $right) as $id) {
            $set[(int) $id] = true;
        }
        $ids = array_map('intval', array_keys($set));
        sort($ids);

        return $ids;
    }

    /**
     * @return Builder<Order>
     */
    private function eligibleQuery(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): Builder
    {
        return $this->rawDateQuery($user, $dateFrom, $dateTo, $mode)
            ->whereNotIn('order_status', self::EXCLUDED_STATUSES)
            ->where(function ($q) {
                $q->whereNull('offer_debt_posted')
                    ->orWhere('offer_debt_posted', 0);
            })
            ->where(function ($q) {
                $q->whereIn('order_status', SalesOrderAccountingService::SHIPPED_PLUS_STATUSES)
                    ->orWhereHas('order_details', function ($details) {
                        $details->whereNotNull('shipping_date');
                    });
            });
    }

    /**
     * @return Builder<Order>
     */
    private function cogsEligibleQuery(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): Builder
    {
        return $this->rawDateQuery($user, $dateFrom, $dateTo, $mode)
            ->whereNotIn('order_status', self::EXCLUDED_STATUSES)
            ->whereIn('order_type', SalesOrderCogsJournalService::ORDER_TYPES)
            ->where(function ($q) {
                $q->whereIn('order_status', SalesOrderAccountingService::SHIPPED_PLUS_STATUSES)
                    ->orWhereHas('order_details', function ($details) {
                        $details->whereNotNull('shipping_date');
                    });
            });
    }

    /**
     * @return Builder<Order>
     */
    private function rawDateQuery(User $user, string $dateFrom, string $dateTo, string $mode = self::MODE_ORDER_DATE): Builder
    {
        $from = Carbon::parse($dateFrom)->toDateString();
        $to = Carbon::parse($dateTo)->toDateString();

        $query = Order::query();
        if ($mode === self::MODE_SHIPPING_DATE) {
            $query->whereIn('id', function ($sub) use ($from, $to) {
                $sub->select('order_id')
                    ->from('order_details')
                    ->whereNotNull('shipping_date')
                    ->whereDate('shipping_date', '>=', $from)
                    ->whereDate('shipping_date', '<=', $to);
            });
        } else {
            $query->whereDate('order_date', '>=', $from)
                ->whereDate('order_date', '<=', $to);
        }

        $this->statusVisibility->applySearchScope($query, $user);

        return $query;
    }

    public static function normalizeMode(string $mode): string
    {
        return $mode === self::MODE_SHIPPING_DATE
            ? self::MODE_SHIPPING_DATE
            : self::MODE_ORDER_DATE;
    }

    public static function maxRangeDaysExceeded(string $dateFrom, string $dateTo, int $maxDays): bool
    {
        return Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) > $maxDays;
    }

    private function findVisibleOrder(User $user, int $orderId): ?Order
    {
        $query = Order::query()->whereKey($orderId);
        $this->statusVisibility->applySearchScope($query, $user);

        return $query->first();
    }
}
