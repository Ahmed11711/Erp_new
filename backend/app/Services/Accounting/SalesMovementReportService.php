<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderStatusVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesMovementReportService
{
    public const MODE_ORDER_DATE = 'order_date';

    public const MODE_JOURNAL_DATE = 'journal_date';

    public const MODE_SHIPPING_DATE = 'shipping_date';

    private const ID_CHUNK = 1500;

    public function __construct(
        private OrderStatusVisibilityService $statusVisibility,
        private OrderAccountingCycleQueryService $cycleQuery,
        private SalesOrderLifecycleJournalService $lifecycle,
        private SalesOrderCogsJournalService $cogsJournal,
    ) {
    }

    /**
     * @return array{
     *     data: list<array<string, mixed>>,
     *     summary: array<string, float|int>,
     *     mode: string,
     *     date_from: string,
     *     date_to: string,
     *     current_page: int,
     *     last_page: int,
     *     per_page: int,
     *     total: int
     * }
     */
    public function report(
        User $user,
        string $dateFrom,
        string $dateTo,
        string $mode = self::MODE_ORDER_DATE,
        ?string $search = null,
        int $page = 1,
        int $perPage = 15,
        bool $missingOnly = false,
        bool $summaryOnly = false
    ): array {
        $mode = $this->normalizeMode($mode);
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        $query = $this->baseOrderQuery($user, $dateFrom, $dateTo, $mode, $search);

        /** @var Collection<int, Order> $meta */
        $meta = $query->get([
            'id',
            'order_date',
            'order_status',
            'offer_debt_posted',
            'order_type',
            'net_total',
            'total_invoice',
        ]);

        $allIds = $meta->pluck('id')->map(fn ($id) => (int) $id)->all();
        $postedInvoiceIds = $this->postedInvoiceOrderIdSet($allIds);
        $postedCogsIds = $this->cogsJournal->postedCogsOrderIdSet($allIds);
        $shippedIds = $this->shippedOrderIdSet($allIds, $meta);

        if ($missingOnly) {
            $meta = $meta->filter(
                fn (Order $order) => $this->isMissingAnyJournal($order, $postedInvoiceIds, $postedCogsIds, $shippedIds)
            )->values();
        }

        $summary = $this->buildSummary($meta, $postedInvoiceIds, $postedCogsIds, $shippedIds, $dateFrom, $dateTo, $mode, $summaryOnly);

        if ($summaryOnly) {
            $total = (int) $summary['orders_count'];

            return $this->emptyPage($summary, $mode, $dateFrom, $dateTo, $page, $perPage, $total);
        }

        $sorted = $meta->sort(function (Order $a, Order $b) use ($postedInvoiceIds, $postedCogsIds, $shippedIds) {
            $aMissing = $this->isMissingAnyJournal($a, $postedInvoiceIds, $postedCogsIds, $shippedIds) ? 0 : 1;
            $bMissing = $this->isMissingAnyJournal($b, $postedInvoiceIds, $postedCogsIds, $shippedIds) ? 0 : 1;
            if ($aMissing !== $bMissing) {
                return $aMissing <=> $bMissing;
            }
            $dateCmp = strcmp((string) $b->order_date, (string) $a->order_date);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return ((int) $b->id) <=> ((int) $a->id);
        })->values();

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $pageMeta = $sorted->forPage($page, $perPage)->values();
        $pageIds = $pageMeta->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ordersById = $this->loadPageOrders($pageIds);
        $summaries = $this->cycleQuery->summariesForOrderIds($pageIds);

        $rows = [];
        foreach ($pageMeta as $metaRow) {
            $orderId = (int) $metaRow->id;
            $order = $ordersById[$orderId] ?? $metaRow;
            $cycle = $summaries[$orderId] ?? [
                'journals_count' => 0,
                'lines_count' => 0,
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'has_invoice' => false,
                'journal_types' => [],
            ];

            $hasInvoice = (bool) ($cycle['has_invoice'] ?? false) || isset($postedInvoiceIds[$orderId]);
            $missingInvoice = $this->isMissingInvoice($order, $postedInvoiceIds, $shippedIds);
            $missingCogs = $this->isMissingCogs($order, $postedCogsIds, $shippedIds);
            $stages = $this->lifecycle->stagesForOrder($order, [], $cycle['journal_types'] ?? []);
            $missingStages = array_values(array_filter($stages, fn (array $stage) => ! empty($stage['missing'])));

            $rows[] = [
                'order_id' => $orderId,
                'order_date' => $order->order_date,
                'movement_date' => $this->movementDate($order, $mode),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone_1,
                'order_status' => $order->order_status,
                'order_type' => $order->order_type,
                'shipping_company' => $order->order_details?->shipping_company?->name,
                'shipping_date' => $order->order_details?->shipping_date
                    ? substr((string) $order->order_details->shipping_date, 0, 10)
                    : null,
                'delivery_date' => $order->order_details?->delivery_date
                    ? substr((string) $order->order_details->delivery_date, 0, 10)
                    : null,
                'net_total' => round((float) ($order->net_total ?? 0), 2),
                'prepaid_amount' => round((float) ($order->prepaid_amount ?? 0), 2),
                'total_invoice' => round((float) ($order->total_invoice ?? 0), 2),
                'journals_count' => (int) ($cycle['journals_count'] ?? 0),
                'lines_count' => (int) ($cycle['lines_count'] ?? 0),
                'total_debit' => round((float) ($cycle['total_debit'] ?? 0), 2),
                'total_credit' => round((float) ($cycle['total_credit'] ?? 0), 2),
                'has_invoice_journal' => $hasInvoice,
                'has_cogs_journal' => isset($postedCogsIds[$orderId]),
                'missing_invoice' => $missingInvoice,
                'missing_cogs' => $missingCogs,
                'missing_lifecycle' => $missingStages !== [],
                'missing_stage_titles' => array_values(array_map(fn (array $stage) => $stage['title'], $missingStages)),
                'stages' => $stages,
                'journal_types' => $cycle['journal_types'] ?? [],
                'journals' => [],
            ];
        }

        return [
            'data' => $rows,
            'summary' => $summary,
            'mode' => $mode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    /**
     * @param  array<string, float|int|string>  $summary
     * @return array<string, mixed>
     */
    private function emptyPage(
        array $summary,
        string $mode,
        string $dateFrom,
        string $dateTo,
        int $page,
        int $perPage,
        int $total
    ): array {
        return [
            'data' => [],
            'summary' => $summary,
            'mode' => $mode,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil(max(0, $total) / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, [
            self::MODE_ORDER_DATE,
            self::MODE_JOURNAL_DATE,
            self::MODE_SHIPPING_DATE,
        ], true) ? $mode : self::MODE_ORDER_DATE;
    }

    private function baseOrderQuery(
        User $user,
        string $dateFrom,
        string $dateTo,
        string $mode,
        ?string $search
    ): Builder {
        $query = Order::query();
        $this->statusVisibility->applySearchScope($query, $user);

        if ($mode === self::MODE_SHIPPING_DATE) {
            $query->whereIn('id', function ($sub) use ($dateFrom, $dateTo) {
                $sub->select('order_id')
                    ->from('order_details')
                    ->whereBetween('shipping_date', [$dateFrom, $dateTo]);
            });
        } elseif ($mode === self::MODE_JOURNAL_DATE) {
            $this->constrainJournalDate($query, $dateFrom, $dateTo);
        } else {
            $query->whereBetween('order_date', [$dateFrom, $dateTo]);
        }

        $term = trim((string) $search);
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
                $like = '%'.$term.'%';
                $q->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone_1', 'like', $like)
                    ->orWhereHas('order_details.shipping_company', function ($co) use ($like) {
                        $co->where('name', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function constrainJournalDate(Builder $query, string $dateFrom, string $dateTo): void
    {
        $query->where(function ($q) use ($dateFrom, $dateTo) {
            $q->whereIn('id', function ($sub) use ($dateFrom, $dateTo) {
                $sub->select('account_entries.order_id')
                    ->from('account_entries')
                    ->join('daily_entries', 'daily_entries.id', '=', 'account_entries.daily_entry_id')
                    ->whereNotNull('account_entries.order_id')
                    ->whereBetween('daily_entries.date', [$dateFrom, $dateTo]);
            })->orWhereIn('id', function ($sub) use ($dateFrom, $dateTo) {
                $sub->select('order_id')
                    ->from('account_entries')
                    ->whereNotNull('order_id')
                    ->whereNull('daily_entry_id')
                    ->where('created_at', '>=', $dateFrom.' 00:00:00')
                    ->where('created_at', '<=', $dateTo.' 23:59:59');
            });
        });
    }

    /**
     * @param  list<int>  $orderIds
     * @return array<int, true>
     */
    private function postedInvoiceOrderIdSet(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter($orderIds)));
        if ($ids === []) {
            return [];
        }

        $posted = [];
        foreach (array_chunk($ids, self::ID_CHUNK) as $chunk) {
            $rows = AccountEntry::query()
                ->whereIn('order_id', $chunk)
                ->whereRaw("entry_batch_code LIKE CONCAT('ORD-', order_id, '-%')")
                ->distinct()
                ->pluck('order_id');
            foreach ($rows as $id) {
                $posted[(int) $id] = true;
            }
        }

        return $posted;
    }

    /**
     * @param  array<int, true>  $postedInvoiceIds
     * @param  array<int, true>  $shippedIds
     */
    private function isMissingInvoice(Order $order, array $postedInvoiceIds, array $shippedIds): bool
    {
        if (in_array((string) $order->order_status, ['ملغي', 'أرشيف'], true)) {
            return false;
        }
        if (! empty($order->offer_debt_posted)) {
            return false;
        }
        if (! isset($shippedIds[(int) $order->id])) {
            return false;
        }

        return ! isset($postedInvoiceIds[(int) $order->id]);
    }

    /**
     * @param  array<int, true>  $postedCogsIds
     * @param  array<int, true>  $shippedIds
     */
    private function isMissingCogs(Order $order, array $postedCogsIds, array $shippedIds): bool
    {
        if (in_array((string) $order->order_status, ['ملغي', 'أرشيف'], true)) {
            return false;
        }
        if (! in_array((string) $order->order_type, SalesOrderCogsJournalService::ORDER_TYPES, true)) {
            return false;
        }
        if (! isset($shippedIds[(int) $order->id])) {
            return false;
        }
        if (isset($postedCogsIds[(int) $order->id])) {
            return false;
        }

        return $this->cogsJournal->composeCogsAmounts($order)['total'] > 0.00001;
    }

    /**
     * @param  array<int, true>  $postedInvoiceIds
     * @param  array<int, true>  $postedCogsIds
     * @param  array<int, true>  $shippedIds
     */
    private function isMissingAnyJournal(Order $order, array $postedInvoiceIds, array $postedCogsIds, array $shippedIds): bool
    {
        return $this->isMissingInvoice($order, $postedInvoiceIds, $shippedIds)
            || $this->isMissingCogs($order, $postedCogsIds, $shippedIds);
    }

    /**
     * @param  list<int>  $orderIds
     * @param  Collection<int, Order>  $meta
     * @return array<int, true>
     */
    private function shippedOrderIdSet(array $orderIds, Collection $meta): array
    {
        $shipped = [];
        foreach ($meta as $order) {
            if (in_array((string) $order->order_status, SalesOrderAccountingService::SHIPPED_PLUS_STATUSES, true)) {
                $shipped[(int) $order->id] = true;
            }
        }

        $ids = array_values(array_unique(array_filter($orderIds)));
        if ($ids === []) {
            return $shipped;
        }

        $dated = \App\Models\OrderDetails::query()
            ->whereIn('order_id', $ids)
            ->whereNotNull('shipping_date')
            ->pluck('order_id');
        foreach ($dated as $id) {
            $shipped[(int) $id] = true;
        }

        return $shipped;
    }

    /**
     * @param  Collection<int, Order>  $meta
     * @param  array<int, true>  $postedInvoiceIds
     * @param  array<int, true>  $postedCogsIds
     * @param  array<int, true>  $shippedIds
     * @return array<string, float|int|string>
     */
    private function buildSummary(
        Collection $meta,
        array $postedInvoiceIds,
        array $postedCogsIds,
        array $shippedIds,
        string $dateFrom,
        string $dateTo,
        string $mode,
        bool $skipJournalTotals
    ): array {
        $ids = $meta->pluck('id')->map(fn ($id) => (int) $id)->all();
        $journalDebit = 0.0;
        $journalCredit = 0.0;
        if (! $skipJournalTotals && $ids !== []) {
            [$journalDebit, $journalCredit] = $this->journalTotalsForOrderIds($ids);
        }

        $missingInvoiceCount = $meta->filter(
            fn (Order $order) => $this->isMissingInvoice($order, $postedInvoiceIds, $shippedIds)
        )->count();
        $missingCogsCount = $meta->filter(
            fn (Order $order) => $this->isMissingCogs($order, $postedCogsIds, $shippedIds)
        )->count();
        $missingJournalCount = $meta->filter(
            fn (Order $order) => $this->isMissingAnyJournal($order, $postedInvoiceIds, $postedCogsIds, $shippedIds)
        )->count();

        return [
            'orders_count' => $meta->count(),
            'invoice_total' => round((float) $meta->sum('total_invoice'), 2),
            'invoice_net' => round((float) $meta->sum('net_total'), 2),
            'journal_debit' => round($journalDebit, 2),
            'journal_credit' => round($journalCredit, 2),
            'missing_invoice_count' => $missingInvoiceCount,
            'missing_cogs_count' => $missingCogsCount,
            'missing_journal_count' => $missingJournalCount,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
        ];
    }

    /**
     * @param  list<int>  $orderIds
     * @return array{0: float, 1: float}
     */
    private function journalTotalsForOrderIds(array $orderIds): array
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach (array_chunk($orderIds, self::ID_CHUNK) as $chunk) {
            $row = AccountEntry::query()
                ->whereIn('order_id', $chunk)
                ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')
                ->first();
            $debit += (float) ($row->d ?? 0);
            $credit += (float) ($row->c ?? 0);
        }

        return [$debit, $credit];
    }

    /**
     * @param  list<int>  $pageIds
     * @return array<int, Order>
     */
    private function loadPageOrders(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        return Order::query()
            ->with([
                'order_details:id,order_id,shipping_date,delivery_date,canceled_date,shipping_company_id,shipping_receivable_amount,remaining_amount,collection_receivable_amount',
                'order_details.shipping_company:id,name,receivable_tree_account_id',
            ])
            ->whereIn('id', $pageIds)
            ->get([
                'id',
                'order_date',
                'customer_name',
                'customer_phone_1',
                'order_status',
                'order_type',
                'net_total',
                'prepaid_amount',
                'total_invoice',
                'offer_debt_posted',
            ])
            ->keyBy(fn (Order $order) => (int) $order->id)
            ->all();
    }

    private function movementDate(Order $order, string $mode): ?string
    {
        if ($mode === self::MODE_SHIPPING_DATE) {
            $ship = $order->order_details?->shipping_date;

            return $ship ? substr((string) $ship, 0, 10) : null;
        }

        if ($mode === self::MODE_JOURNAL_DATE) {
            return null;
        }

        return $order->order_date ? substr((string) $order->order_date, 0, 10) : null;
    }
}
