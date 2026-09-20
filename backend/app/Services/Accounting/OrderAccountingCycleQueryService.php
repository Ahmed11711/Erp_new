<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * قراءة فقط: تجميع قيود اليومية المرتبطة بطلب لعرض الدورة المحاسبية في التفاصيل.
 * لا يرحّل ولا يعدّل قيوداً.
 */
class OrderAccountingCycleQueryService
{
    /**
     * @return array{
     *     order_id: int,
     *     journals_count: int,
     *     lines_count: int,
     *     total_debit: float,
     *     total_credit: float,
     *     journals: list<array<string, mixed>>
     * }
     */
    public function forOrder(Order $order): array
    {
        $cycle = $this->mapCycle($order, $this->loadEntries($order));
        $cycle['stages'] = app(SalesOrderLifecycleJournalService::class)
            ->stagesForOrder($order, $cycle['journals']);

        return $cycle;
    }

    /**
     * @param  Collection<int, AccountEntry>  $entries
     * @return array{
     *     order_id: int,
     *     journals_count: int,
     *     lines_count: int,
     *     total_debit: float,
     *     total_credit: float,
     *     journals: list<array<string, mixed>>
     * }
     */
    private function mapCycle(Order $order, Collection $entries): array
    {
        $journals = $entries
            ->groupBy(fn (AccountEntry $entry) => $this->groupKey($entry))
            ->map(fn (Collection $lines) => $this->mapJournal($lines))
            ->sortBy(fn (array $journal) => $journal['sort_at'])
            ->values()
            ->map(function (array $journal) {
                unset($journal['sort_at']);

                return $journal;
            })
            ->all();

        return [
            'order_id' => (int) $order->id,
            'journals_count' => count($journals),
            'lines_count' => $entries->count(),
            'total_debit' => round((float) $entries->sum('debit'), 2),
            'total_credit' => round((float) $entries->sum('credit'), 2),
            'journals' => $journals,
        ];
    }

    /**
     * @param  iterable<int, Order>  $orders
     * @return array<int, array<string, mixed>>
     */
    public function forMany(iterable $orders): array
    {
        $byId = [];
        foreach ($orders as $order) {
            if ($order instanceof Order) {
                $byId[(int) $order->id] = $order;
            }
        }
        if ($byId === []) {
            return [];
        }

        $grouped = $this->loadEntriesForMany(array_values($byId));

        $out = [];
        foreach ($byId as $id => $order) {
            $mapped = $this->mapCycle($order, $grouped->get($id, collect()));
            $mapped['stages'] = app(SalesOrderLifecycleJournalService::class)
                ->stagesForOrder($order, $mapped['journals']);
            $out[$id] = $mapped;
        }

        return $out;
    }

    /**
     * إحصاءات خفيفة لصفحة القائمة: طلب واحد مفهرس بـ order_id دون LIKE على الوصف.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array{
     *     journals_count: int,
     *     lines_count: int,
     *     total_debit: float,
     *     total_credit: float,
     *     has_invoice: bool,
     *     journal_types: list<array{type: string, title: string}>
     * }>
     */
    public function summariesForOrderIds(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = [
                'journals_count' => 0,
                'lines_count' => 0,
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'has_invoice' => false,
                'journal_types' => [],
            ];
        }
        if ($ids === []) {
            return $out;
        }

        $entries = AccountEntry::query()
            ->whereIn('order_id', $ids)
            ->get(['id', 'order_id', 'entry_batch_code', 'description', 'debit', 'credit', 'daily_entry_id', 'created_at']);

        foreach ($entries->groupBy(fn (AccountEntry $e) => (int) $e->order_id) as $orderId => $rows) {
            $journals = $rows
                ->groupBy(fn (AccountEntry $entry) => $this->groupKey($entry));
            $types = [];
            foreach ($journals as $group) {
                $first = $group->first();
                [$type, $title] = $this->classify(
                    (string) ($first->entry_batch_code ?? ''),
                    (string) ($first->description ?? '')
                );
                $types[$type] = ['type' => $type, 'title' => $title];
            }
            $out[(int) $orderId] = [
                'journals_count' => $journals->count(),
                'lines_count' => $rows->count(),
                'total_debit' => round((float) $rows->sum('debit'), 2),
                'total_credit' => round((float) $rows->sum('credit'), 2),
                'has_invoice' => isset($types['invoice']),
                'journal_types' => array_values($types),
            ];
        }

        return $out;
    }

    /**
     * @return Collection<int, AccountEntry>
     */
    private function loadEntries(Order $order): Collection
    {
        $orderId = (int) $order->id;
        $patterns = $this->batchPatterns($order);

        $query = AccountEntry::query()
            ->with([
                'account:id,code,name,type',
                'dailyEntry:id,entry_number,date,description',
            ])
            ->where(function ($q) use ($orderId, $patterns) {
                $q->where('order_id', $orderId);

                foreach ($patterns as $pattern) {
                    $q->orWhere('entry_batch_code', 'like', $pattern);
                }

                $q->orWhere(function ($descQ) use ($orderId) {
                    $descQ->where('description', 'like', '%طلب رقم '.$orderId.'%')
                        ->orWhere('description', 'like', '%للطلب رقم '.$orderId.'%')
                        ->orWhere('description', 'like', '%طلب '.$orderId.'%');
                });
            })
            ->orderBy('created_at')
            ->orderBy('id');

        return $query->get()->filter(function (AccountEntry $entry) use ($orderId, $patterns) {
            if ((int) $entry->order_id === $orderId) {
                return true;
            }

            $batch = (string) ($entry->entry_batch_code ?? '');
            foreach ($patterns as $pattern) {
                if ($batch !== '' && $this->batchMatches($batch, $pattern)) {
                    return true;
                }
            }

            return $this->descriptionBelongsToOrder((string) $entry->description, $orderId);
        })->values();
    }

    /**
     * تحميل قيود عدة طلبات بطلبين مفهرسين (order_id / بادئة batch) دون LIKE على الوصف.
     *
     * @param  list<Order>  $orders
     * @return Collection<int, Collection<int, AccountEntry>>
     */
    private function loadEntriesForMany(array $orders): Collection
    {
        $ids = array_map(static fn (Order $order) => (int) $order->id, $orders);
        $eager = [
            'account:id,code,name,type',
            'dailyEntry:id,entry_number,date,description',
        ];

        $linked = AccountEntry::query()
            ->with($eager)
            ->whereIn('order_id', $ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $patterns = [];
        foreach ($orders as $order) {
            foreach ($this->batchPatterns($order) as $pattern) {
                $patterns[$pattern] = true;
            }
        }
        $unlinked = collect();
        if ($patterns !== []) {
            $unlinked = AccountEntry::query()
                ->with($eager)
                ->whereNull('order_id')
                ->where(function ($q) use ($patterns) {
                    foreach (array_keys($patterns) as $pattern) {
                        $q->orWhere('entry_batch_code', 'like', $pattern);
                    }
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        $byOrder = $linked->groupBy(fn (AccountEntry $entry) => (int) $entry->order_id);
        foreach ($unlinked as $entry) {
            $batch = (string) ($entry->entry_batch_code ?? '');
            foreach ($orders as $order) {
                $id = (int) $order->id;
                foreach ($this->batchPatterns($order) as $pattern) {
                    if ($batch !== '' && $this->batchMatches($batch, $pattern)) {
                        $existing = $byOrder->get($id, collect());
                        $byOrder->put($id, $existing->push($entry));
                        break 2;
                    }
                }
            }
        }

        return $byOrder;
    }

    /**
     * @return list<string>
     */
    private function batchPatterns(Order $order): array
    {
        $id = (int) $order->id;
        $patterns = [
            'ORD-'.$id.'-%',
            'ORD-PREPAID-'.$id.'-%',
            'ORD-PREPAID-PENDING-'.$id.'-%',
            'ORD-OPS-'.$id.'-%',
            'PARTCOLLECT-'.$id.'-%',
            'PREPAID-REV-'.$id.'-%',
            'DELIVERY-'.$id.'-%',
            'COGS-'.$id.'-%',
            'RETURN-SALES-'.$id.'-%',
            'RETURN-COGS-'.$id.'-%',
            'SHIP-COST-'.$id.'-%',
            'ROLLBACK-'.$id.'-%',
            'ROLLBACK-COGS-'.$id.'-%',
        ];

        $offerId = (int) ($order->offer_id ?? 0);
        if ($offerId > 0) {
            $patterns[] = 'OFFER-'.$offerId.'-%';
        }

        return $patterns;
    }

    private function batchMatches(string $batch, string $likePattern): bool
    {
        $regex = '/^'.str_replace('%', '.*', preg_quote($likePattern, '/')).'$/';

        return (bool) preg_match($regex, $batch);
    }

    private function descriptionBelongsToOrder(string $description, int $orderId): bool
    {
        if ($description === '') {
            return false;
        }

        return (bool) preg_match(
            '/(?:طلب(?:\s+رقم)?|للطلب رقم)\s*'.$orderId.'(?!\d)/u',
            $description
        );
    }

    private function groupKey(AccountEntry $entry): string
    {
        if ($entry->daily_entry_id) {
            return 'de:'.(int) $entry->daily_entry_id;
        }

        $batch = trim((string) ($entry->entry_batch_code ?? ''));
        if ($batch !== '') {
            return 'batch:'.$batch;
        }

        $desc = trim((string) ($entry->description ?? ''));
        $stamp = optional($entry->created_at)->format('Y-m-d H:i') ?? 'na';

        return 'loose:'.md5($desc.'|'.$stamp);
    }

    /**
     * @param  Collection<int, AccountEntry>  $lines
     * @return array<string, mixed>
     */
    private function mapJournal(Collection $lines): array
    {
        $first = $lines->first();
        $batch = (string) ($first->entry_batch_code ?? '');
        $headerDesc = (string) ($first->dailyEntry?->description ?: $first->description);
        [$type, $title] = $this->classify($batch, $headerDesc);

        $mappedLines = $lines->map(function (AccountEntry $line) {
            return [
                'id' => (int) $line->id,
                'account_id' => (int) $line->tree_account_id,
                'account_code' => $line->account?->code,
                'account_name' => $line->account?->name,
                'description' => $line->description,
                'debit' => round((float) $line->debit, 2),
                'credit' => round((float) $line->credit, 2),
            ];
        })->values()->all();

        $createdAt = $first->created_at;

        return [
            'key' => $this->groupKey($first),
            'type' => $type,
            'stage' => app(SalesOrderLifecycleJournalService::class)->classifyStage($type),
            'title' => $title,
            'batch_code' => $batch !== '' ? $batch : null,
            'daily_entry_id' => $first->daily_entry_id ? (int) $first->daily_entry_id : null,
            'entry_number' => $first->dailyEntry?->entry_number,
            'date' => $first->dailyEntry?->date?->format('Y-m-d')
                ?? optional($createdAt)->format('Y-m-d'),
            'created_at' => optional($createdAt)?->toIso8601String(),
            'description' => $headerDesc,
            'total_debit' => round((float) $lines->sum('debit'), 2),
            'total_credit' => round((float) $lines->sum('credit'), 2),
            'lines' => $mappedLines,
            'sort_at' => optional($createdAt)?->format('Y-m-d H:i:s') ?? '9999',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function classify(string $batch, string $description): array
    {
        $isReversal = str_contains($description, 'عكس')
            || str_contains($description, 'إرجاع تكلفة')
            || str_starts_with($batch, 'ROLLBACK-')
            || str_starts_with($batch, 'PREPAID-REV-');

        if (
            str_contains($batch, 'ROLLBACK-COGS')
            || preg_match('/تكلفة البضاعة|تكلفة مبيعات|COGS|إرجاع تكلفة للمخزون/u', $description)
        ) {
            return $isReversal || str_starts_with($batch, 'RETURN-COGS-')
                ? ['cogs_reversal', 'عكس تكلفة البضاعة المباعة']
                : ['cogs', 'تكلفة البضاعة المباعة'];
        }

        if (str_starts_with($batch, 'ORD-PREPAID-PENDING-')) {
            return ['prepaid_pending', 'دفعة مقدمة بانتظار التسجيل'];
        }
        if (str_starts_with($batch, 'ORD-PREPAID-')) {
            return $isReversal ? ['prepaid_reversal', 'عكس دفعة مقدمة'] : ['prepaid', 'تاريخ السداد المقدم'];
        }
        if (str_starts_with($batch, 'PREPAID-REV-')) {
            return ['prepaid_reversal', 'رد قيمة السداد للعميل'];
        }
        if (str_starts_with($batch, 'ORD-OPS-') || str_starts_with($batch, 'PARTCOLLECT-')) {
            return $isReversal ? ['reversal', 'عكس تحصيل'] : ['collection', 'تاريخ السداد المقدم'];
        }
        if (str_starts_with($batch, 'RETURN-SALES-')) {
            return ['return_sales', 'تسوية قيد المبيعات — رد بضاعة / رفض استلام'];
        }
        if (str_starts_with($batch, 'RETURN-COGS-') || str_starts_with($batch, 'COGS-')) {
            return $isReversal || str_starts_with($batch, 'RETURN-COGS-')
                ? ['cogs_reversal', 'عكس تكلفة البضاعة المباعة']
                : ['cogs', 'تكلفة البضاعة المباعة'];
        }
        if (str_starts_with($batch, 'DELIVERY-')) {
            return $isReversal
                ? ['delivery_reversal', 'عكس نقل الذمة عند التسليم']
                : ['delivery', 'نقل الذمة إلى شركة الشحن'];
        }
        if (str_starts_with($batch, 'SHIP-COST-')) {
            return $isReversal
                ? ['courier_cost_reversal', 'عكس مصروف شحن المندوب']
                : ['courier_cost', 'مصروف شحن المندوب / شركة الشحن'];
        }
        if (str_starts_with($batch, 'OFFER-')) {
            return ['invoice', 'إثبات فاتورة (عرض سعر)'];
        }
        if (preg_match('/^ORD-\d+-/', $batch)) {
            return $isReversal ? ['reversal', 'عكس فاتورة المبيعات'] : ['invoice', 'إثبات المبيعات — تاريخ الشحن'];
        }
        if ($isReversal) {
            return ['reversal', 'عكس قيد'];
        }

        return ['other', 'قيد مرتبط بالطلب'];
    }
}
