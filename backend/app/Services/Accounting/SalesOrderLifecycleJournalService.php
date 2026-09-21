<?php

namespace App\Services\Accounting;

use App\Enums\OrderCollectionStatus;
use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\TreeAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * التقويم المعتمد لقيود الطلب حسب الحالة:
 * 1) تاريخ السداد المقدم — من حـ/ شركة التحصيل  إلى حـ/ العميل
 * 2) تاريخ الشحن — تكلفة المخزون + إثبات المبيعات على العميل
 * 3) تاريخ التسليم / رفض الاستلام — من حـ/ شركة الشحن  إلى حـ/ العميل (المتبقي بعد المقدم)
 * 4) رد البضاعة / رفض الاستلام — تسوية إيرادات المبيعات + عكس التكلفة
 * 5) رد قيمة السداد — من حـ/ العميل  إلى حـ/ جهة السداد
 */
class SalesOrderLifecycleJournalService
{
    public const STAGE_ADVANCE = 'advance_payment';

    public const STAGE_SHIPPING = 'shipping_recognition';

    public const STAGE_COGS = 'cogs_recognition';

    public const STAGE_DELIVERY = 'delivery_transfer';

    public const STAGE_RETURN = 'return_adjustment';

    public const STAGE_REFUND = 'customer_refund';

    private const SHIPPED_PLUS = SalesOrderAccountingService::SHIPPED_PLUS_STATUSES;

    private const DELIVERY_PLUS = SalesOrderAccountingService::DELIVERED_PLUS_STATUSES;

    private const RETURN_STATUSES = [
        'رفض استلام',
        'مرتجع',
    ];

    public function __construct(
        private LedgerJournalService $journal,
        private SalesOrderAccountingService $salesOrderAccounting,
        private DeliveryConfirmationAccountingService $deliveryAccounting,
        private SalesOrderCogsJournalService $cogsJournal,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     * @param  list<array{type: string, title: string}>  $journalTypes
     * @return list<array<string, mixed>>
     */
    public function stagesForOrder(Order $order, array $journals = [], array $journalTypes = []): array
    {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);
        $types = $this->postedTypeSet($journals, $journalTypes);
        $byStage = $this->groupJournalsByStage($journals);

        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        $status = (string) $order->order_status;
        $shippingDate = $this->dateOnly($order->order_details?->shipping_date);
        $deliveryDate = $this->dateOnly($order->order_details?->delivery_date);
        $returnDate = $this->dateOnly($order->order_details?->canceled_date);
        $remaining = $this->remainingReceivable($order);

        $stages = [];

        $advancePosted = $this->hasAnyType($types, [
            'prepaid', 'prepaid_pending', 'collection', 'advance_payment',
        ]);
        $stages[] = $this->mapStage(
            self::STAGE_ADVANCE,
            'تاريخ السداد المقدم',
            'من حـ/ شركة التحصيل  إلى حـ/ العميل',
            $prepaid > 0.009,
            $advancePosted,
            $this->dateOnly($order->order_date),
            $byStage[self::STAGE_ADVANCE] ?? [],
            $prepaid > 0.009 ? $prepaid : null
        );

        $shippingPosted = $this->hasAnyType($types, ['invoice']);
        $shippingApplicable = $this->isShippedOrLater($status, $shippingDate);
        $stages[] = $this->mapStage(
            self::STAGE_SHIPPING,
            'تاريخ الشحن',
            'إثبات المبيعات على العميل (بضاعة/صيانة + إيراد الشحن)',
            $shippingApplicable,
            $shippingPosted,
            $shippingDate,
            $byStage[self::STAGE_SHIPPING] ?? [],
            $shippingApplicable ? round((float) ($order->net_total ?? 0), 2) : null
        );

        $cogsPosted = $this->hasAnyType($types, ['cogs']);
        $cogsApplicable = $shippingApplicable
            && in_array((string) $order->order_type, SalesOrderCogsJournalService::ORDER_TYPES, true)
            && ($cogsPosted || $this->cogsJournal->composeCogsAmounts($order)['total'] > 0.00001);
        $stages[] = $this->mapStage(
            self::STAGE_COGS,
            'تكلفة خروج المخزن التام',
            'من حـ/ تكلفة المبيعات  إلى حـ/ مخزون منتج تام',
            $cogsApplicable,
            $cogsPosted,
            $shippingDate,
            $byStage[self::STAGE_COGS] ?? [],
            null
        );

        $deliveryPosted = $this->hasAnyType($types, ['delivery'])
            || $this->legacyReceivableAlreadyOnShipping($order);
        $deliveryApplicable = $this->isDeliveryOrLater($status, $deliveryDate, $returnDate)
            && $remaining > 0.009;
        $stages[] = $this->mapStage(
            self::STAGE_DELIVERY,
            'تاريخ التسليم',
            'من حـ/ شركة الشحن  إلى حـ/ العميل — المتبقي بعد السداد المقدم',
            $deliveryApplicable || $deliveryPosted,
            $deliveryPosted,
            $deliveryDate ?: $returnDate,
            $byStage[self::STAGE_DELIVERY] ?? [],
            $remaining > 0.009 ? $remaining : null
        );

        $returnPosted = $this->hasAnyType($types, ['return_sales', 'cogs_reversal']);
        $returnApplicable = $this->isReturned($status);
        $stages[] = $this->mapStage(
            self::STAGE_RETURN,
            'تاريخ رد البضاعة / رفض الاستلام',
            'تسوية قيد المبيعات: من حـ/ الإيرادات  إلى حـ/ شركة الشحن + عكس التكلفة',
            $returnApplicable,
            $returnPosted,
            $returnDate,
            $byStage[self::STAGE_RETURN] ?? [],
            $returnApplicable ? round((float) ($order->net_total ?? 0), 2) : null
        );

        $refundPosted = $this->hasAnyType($types, ['prepaid_reversal', 'customer_refund']);
        $stages[] = $this->mapStage(
            self::STAGE_REFUND,
            'رد قيمة السداد للعميل',
            'من حـ/ العميل  إلى حـ/ جهة السداد (خزينة / بنك / فودافون كاش / إنستا باي)',
            $refundPosted,
            $refundPosted,
            $this->firstJournalDate($byStage[self::STAGE_REFUND] ?? []),
            $byStage[self::STAGE_REFUND] ?? [],
            $refundPosted ? $prepaid : null
        );

        return $this->attachStageNotes($order, $status, $stages);
    }

    /**
     * ترحيل القيود الناقصة حسب حالة الطلب دون حذف قيود موجودة.
     *
     * @return array{
     *     prepaid: string|null,
     *     invoice: string|null,
     *     cogs: string|null,
     *     delivery: string|null,
     *     return_sales: string|null,
     *     cogs_reversal: string|null,
     *     posted: list<string>,
     *     skipped: list<string>
     * }
     */
    public function postMissingForOrder(Order $order): array
    {
        $order->loadMissing(['order_products', 'order_details.shipping_company', 'order_details.collection_company']);

        $posted = [];
        $skipped = [];
        $invoice = null;
        $cogs = null;
        $delivery = null;
        $returnSales = null;
        $cogsReversal = null;
        $prepaid = $this->salesOrderAccounting->recordPrepaidIfMissing($order);
        if ($prepaid === 'posted') {
            $posted[] = 'prepaid';
        } else {
            $skipped[] = 'prepaid:'.$prepaid;
        }

        $status = (string) $order->order_status;
        $shippingDate = $this->dateOnly($order->order_details?->shipping_date);

        if ($this->isShippedOrLater($status, $shippingDate)
            && ! $this->salesOrderAccounting->shouldSkipInvoiceRecognition($order)
            && ! $this->salesOrderAccounting->hasInvoiceRecognition($order)
        ) {
            $invoice = $this->salesOrderAccounting->recordInitialOrderRecognitionIfMissing($order);
            if ($invoice === 'posted') {
                $posted[] = 'invoice';
            } else {
                $skipped[] = 'invoice:'.$invoice;
            }
        } else {
            $skipped[] = 'invoice';
        }

        $cogs = $this->cogsJournal->postIfMissing($order);
        if ($cogs === 'posted') {
            $posted[] = 'cogs';
        } else {
            $skipped[] = 'cogs:'.$cogs;
        }

        if ($this->shouldPostDelivery($order, $status)) {
            try {
                $result = $this->deliveryAccounting->recordDeliveryReceivableTransfer(
                    $order,
                    $this->resolveDeliveryJournalDate($order)
                );
                $delivery = ($result['transferred_amount'] ?? 0) > 0.009 ? 'posted' : 'skipped_zero';
                if ($delivery === 'posted' && ! empty($result['batch_code'])) {
                    $posted[] = 'delivery';
                } else {
                    $skipped[] = 'delivery';
                }
            } catch (\Throwable $e) {
                Log::warning('SalesOrderLifecycle: delivery transfer failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $delivery = 'failed';
                $skipped[] = 'delivery:failed';
            }
        } else {
            $skipped[] = 'delivery';
        }

        if ($this->isReturned($status) && ! $this->hasReturnSalesAdjustment($order)) {
            $returnSales = $this->postReturnSalesAdjustment($order, $this->resolveReturnJournalDate($order));
            if ($returnSales === 'posted') {
                $posted[] = 'return_sales';
            } else {
                $skipped[] = 'return_sales:'.$returnSales;
            }
        } else {
            $skipped[] = 'return_sales';
        }

        $cogsReversal = $this->cogsJournal->postReversalIfMissing($order, $this->resolveReturnJournalDate($order));
        if ($cogsReversal === 'posted') {
            $posted[] = 'cogs_reversal';
        } else {
            $skipped[] = 'cogs_reversal:'.$cogsReversal;
        }

        return [
            'prepaid' => $prepaid,
            'invoice' => $invoice,
            'cogs' => $cogs,
            'delivery' => $delivery,
            'return_sales' => $returnSales,
            'cogs_reversal' => $cogsReversal,
            'posted' => $posted,
            'skipped' => $skipped,
        ];
    }

    /**
     * مسودة قيد تاريخ التسليم (نقل الذمة من العميل إلى شركة الشحن).
     *
     * @return array{
     *     applicable: bool,
     *     posted: bool,
     *     can_post: bool,
     *     reason: string|null,
     *     date: string,
     *     description: string,
     *     total: float,
     *     lines: list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     * }
     */
    public function previewDelivery(Order $order): array
    {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);
        $status = (string) $order->order_status;
        $draft = $this->deliveryAccounting->composeTransferDraft($order);
        $posted = $this->deliveryAccounting->hasDeliveryBatch($order)
            || $this->legacyReceivableAlreadyOnShipping($order);
        $deliveryDate = $this->dateOnly($order->order_details?->delivery_date);
        $returnDate = $this->dateOnly($order->order_details?->canceled_date);
        $ready = $this->isDeliveryOrLater($status, $deliveryDate, $returnDate);
        $remaining = $this->remainingReceivable($order);

        $draft['posted'] = $posted;
        $draft['date'] = $this->resolveDeliveryJournalDate($order)?->toDateString() ?? $draft['date'];
        if ($posted) {
            $draft['applicable'] = true;
            $draft['can_post'] = false;
            $draft['reason'] = $this->deliveryAccounting->hasDeliveryBatch($order)
                ? 'قيد نقل الذمة عند التسليم موجود مسبقاً.'
                : 'الذمة مسجّلة على شركة الشحن من قيد إثبات المبيعات.';
            $draft['lines'] = [];

            return $draft;
        }

        if (! $ready) {
            $draft['applicable'] = false;
            $draft['can_post'] = false;
            $draft['reason'] = 'الطلب لم يُسلَّم بعد — قيد نقل الذمة يُرحَّل بتاريخ التسليم.';

            return $draft;
        }

        if ($remaining <= 0.009) {
            $draft['applicable'] = false;
            $draft['can_post'] = false;
            $draft['reason'] = 'لا يوجد مبلغ متبقي لنقله إلى شركة الشحن بعد السداد المقدم.';

            return $draft;
        }

        $draft['applicable'] = true;
        if ($draft['can_post'] && $draft['lines'] !== []) {
            $draft['reason'] = null;

            return $draft;
        }

        $draft['can_post'] = false;
        if (! $draft['reason']) {
            $draft['reason'] = 'تعذر بناء قيد التسليم: حساب ذمة شركة الشحن غير مربوط أو لا يوجد مبلغ للنقل.';
        }

        return $draft;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function recordDeliveryWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null
    ): string {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);
        if (! $this->shouldPostDelivery($order, (string) $order->order_status)) {
            return $this->deliveryAccounting->hasDeliveryBatch($order) ? 'skipped_exists' : 'skipped_ineligible';
        }

        return $this->deliveryAccounting->recordTransferWithLines($order, $lines, $description, $journalDate);
    }

    public function hasReturnSalesAdjustment(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'RETURN-SALES-'.$order->id.'-%')
            ->exists();
    }

    /**
     * تسوية قيد المبيعات عند رد البضاعة / رفض الاستلام:
     * من حـ/ إيرادات المبيعات + إيراد الشحن  إلى حـ/ شركة الشحن
     *
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function postReturnSalesAdjustment(Order $order, ?\DateTimeInterface $journalDate = null): string
    {
        if ($this->hasReturnSalesAdjustment($order)) {
            return 'skipped_exists';
        }

        $order->loadMissing(['order_products', 'order_details.shipping_company']);
        $composed = $this->salesOrderAccounting->composeInvoiceRecognitionLines($order);
        if ($composed === null) {
            return 'skipped_ineligible';
        }

        $shippingAccountId = $this->resolveShippingReceivableAccountId($order);
        $customerAccount = $this->resolveCustomerAccount($order);
        $creditAccountId = $shippingAccountId ?: ($customerAccount?->id);
        if (! $creditAccountId) {
            Log::warning('SalesOrderLifecycle: cannot resolve credit account for return adjustment', [
                'order_id' => $order->id,
            ]);

            return 'failed';
        }

        $lines = [];
        $creditTotal = 0.0;
        foreach ($composed['lines'] as $line) {
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($credit <= 0.009) {
                continue;
            }
            $creditTotal += $credit;
            $lines[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $credit,
                'credit' => 0.0,
                'description' => 'تسوية قيد المبيعات — عكس '.$line['description'],
            ];
        }

        if ($creditTotal <= 0.009 || $lines === []) {
            return 'skipped_ineligible';
        }

        $companyName = (string) ($order->order_details?->shipping_company?->name ?? 'شركة الشحن');
        $lines[] = [
            'account_id' => (int) $creditAccountId,
            'debit' => 0.0,
            'credit' => round($creditTotal, 2),
            'description' => 'تسوية قيد المبيعات — تخفيض ذمة '.$companyName,
        ];

        $date = $journalDate ?? $this->resolveReturnJournalDate($order);
        $batch = 'RETURN-SALES-'.$order->id.'-'.now()->format('YmdHis');
        $desc = 'تسوية قيد المبيعات — رد بضاعة / رفض استلام — طلب رقم '.$order->id;

        $this->journal->postBalancedJournal($lines, $desc, $order->id, $batch, null, $date);

        return $this->hasReturnSalesAdjustment($order) ? 'posted' : 'failed';
    }

    public function classifyStage(string $type): string
    {
        return match ($type) {
            'prepaid', 'prepaid_pending', 'collection', 'advance_payment' => self::STAGE_ADVANCE,
            'invoice' => self::STAGE_SHIPPING,
            'cogs' => self::STAGE_COGS,
            'delivery' => self::STAGE_DELIVERY,
            'return_sales', 'cogs_reversal' => self::STAGE_RETURN,
            'prepaid_reversal', 'customer_refund' => self::STAGE_REFUND,
            default => 'other',
        };
    }

    private function shouldPostDelivery(Order $order, string $status): bool
    {
        if ($this->remainingReceivable($order) <= 0.009) {
            return false;
        }

        $deliveryDate = $this->dateOnly($order->order_details?->delivery_date);
        $returnDate = $this->dateOnly($order->order_details?->canceled_date);
        if (! $this->isDeliveryOrLater($status, $deliveryDate, $returnDate)) {
            return false;
        }

        if (AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-'.$order->id.'-%')
            ->exists()) {
            return false;
        }

        return ! $this->legacyReceivableAlreadyOnShipping($order);
    }

    private function legacyReceivableAlreadyOnShipping(Order $order): bool
    {
        $shippingAccountId = $this->resolveShippingReceivableAccountId($order);
        if (! $shippingAccountId) {
            return false;
        }

        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'ORD-'.$order->id.'-%')
            ->where('tree_account_id', $shippingAccountId)
            ->where('debit', '>', 0.009)
            ->exists();
    }

    private function remainingReceivable(Order $order): float
    {
        $total = round((float) ($order->net_total ?? 0), 2);
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        $od = $order->order_details;

        // بعد «تم التحصيل» يصفّر النظام remaining_amount / shipping_receivable_amount تشغيلياً،
        // لكن قيد نقل الذمة عند التسليم يبقى مطلوباً بقيمة المتبقي بعد السداد المقدم.
        $collected = $this->isCollected($order);

        if ($od && ($od->shipping_receivable_amount !== null || $od->remaining_amount !== null)) {
            if ($od->shipping_receivable_amount !== null) {
                $stored = round(max(0, (float) $od->shipping_receivable_amount), 2);
                if ($stored > 0.009 || ! $collected) {
                    return $stored;
                }
            }
            if ($od->remaining_amount !== null) {
                $stored = round(max(0, (float) $od->remaining_amount), 2);
                if ($stored > 0.009 || ! $collected) {
                    return $stored;
                }
            }
        }

        return round(max(0, $total - $prepaid), 2);
    }

    private function isCollected(Order $order): bool
    {
        if ((string) $order->order_status === 'تم التحصيل') {
            return true;
        }

        return ($order->order_details?->collection_status ?? null) === OrderCollectionStatus::Collected->value;
    }

    private function isShippedOrLater(string $status, ?string $shippingDate): bool
    {
        return $shippingDate !== null || in_array($status, self::SHIPPED_PLUS, true);
    }

    private function isDeliveryOrLater(string $status, ?string $deliveryDate, ?string $returnDate): bool
    {
        return $deliveryDate !== null
            || $returnDate !== null
            || in_array($status, self::DELIVERY_PLUS, true);
    }

    private function isReturned(string $status): bool
    {
        return in_array($status, self::RETURN_STATUSES, true);
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     * @param  list<array{type: string, title: string}>  $journalTypes
     * @return array<string, true>
     */
    private function postedTypeSet(array $journals, array $journalTypes): array
    {
        $types = [];
        foreach ($journals as $journal) {
            $type = (string) ($journal['type'] ?? '');
            if ($type !== '') {
                $types[$type] = true;
            }
        }
        foreach ($journalTypes as $row) {
            $type = (string) ($row['type'] ?? '');
            if ($type !== '') {
                $types[$type] = true;
            }
        }

        return $types;
    }

    /**
     * @param  array<string, true>  $types
     * @param  list<string>  $needles
     */
    private function hasAnyType(array $types, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (isset($types[$needle])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupJournalsByStage(array $journals): array
    {
        $grouped = [];
        foreach ($journals as $journal) {
            $stage = $this->classifyStage((string) ($journal['type'] ?? 'other'));
            $grouped[$stage][] = $journal;
        }

        return $grouped;
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     * @return array<string, mixed>
     */
    private function mapStage(
        string $key,
        string $title,
        string $rule,
        bool $applicable,
        bool $posted,
        ?string $approvedDate,
        array $journals,
        ?float $amount
    ): array {
        $missing = $applicable && ! $posted;

        return [
            'key' => $key,
            'title' => $title,
            'rule' => $rule,
            'applicable' => $applicable,
            'posted' => $posted,
            'missing' => $missing,
            'approved_date' => $approvedDate,
            'amount' => $amount,
            'journals' => $journals,
            'notes' => [],
            'anomaly' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     * @return list<array<string, mixed>>
     */
    private function attachStageNotes(Order $order, string $status, array $stages): array
    {
        $cogsIndex = $this->stageIndex($stages, self::STAGE_COGS);
        if ($cogsIndex === null) {
            return $stages;
        }

        $cogsJournals = $stages[$cogsIndex]['journals'] ?? [];
        $cogsCount = count($cogsJournals);
        if ($cogsCount <= 1) {
            return $stages;
        }

        $reversalCount = 0;
        $reversalAmount = 0.0;
        foreach ($stages as $stage) {
            foreach ($stage['journals'] ?? [] as $journal) {
                if (! $this->isCogsReversalJournal($journal)) {
                    continue;
                }
                $reversalCount++;
                $reversalAmount += round((float) ($journal['total_debit'] ?? 0), 2);
            }
        }

        $cogsAmount = 0.0;
        foreach ($cogsJournals as $index => $journal) {
            $cogsAmount += round((float) ($journal['total_debit'] ?? 0), 2);
            $date = $this->dateOnly($journal['date'] ?? null) ?? '—';
            $stages[$cogsIndex]['journals'][$index]['note'] = $index === 0
                ? 'القيد الأصلي عند أول شحن بتاريخ '.$date.' — هذا هو قيد التكلفة الصحيح للشحنة.'
                : 'تكرار محاسبي: قيد خروج إضافي رقم '.($index + 1).' بتاريخ '.$date.' بعد إعادة الشحن دون عكس قيد التكلفة السابق.';
        }

        $net = round($cogsAmount - $reversalAmount, 2);
        $shipCount = $this->shipmentCount($order);
        $postponeCount = $this->postponeCount($order);
        $money = static fn (float $value): string => number_format($value, 2, '.', '');

        $notes = [
            [
                'level' => 'warning',
                'text' => 'هذه القيود ليست عرضاً مكرراً في الشاشة. كل قيد مُرحَّل فعلاً إلى الحسابات (مدين تكلفة المبيعات / دائن مخزون منتج تام).',
            ],
            [
                'level' => 'warning',
                'text' => 'محاسبياً هذا تكرار غير صحيح. التأجيل بعد الشحن يرجع الكمية للمخزن ويسمح بإعادة الشحن، لكنه لا يعكس قيد تكلفة البضاعة المباعة، فيتراكم نفس المبلغ مع كل شحنة جديدة.',
            ],
        ];

        $counts = 'قيود الخروج: '.$cogsCount.' — قيود العكس: '.$reversalCount;
        if ($shipCount > 0) {
            $counts .= ' — عدد الشحنات المسجّلة: '.$shipCount;
        }
        if ($postponeCount > 0) {
            $counts .= ' — مرات التأجيل: '.$postponeCount;
        }
        $notes[] = [
            'level' => 'info',
            'text' => $counts.'.',
        ];

        if ($this->isReturned($status)) {
            $notes[] = [
                'level' => 'warning',
                'text' => 'رفض الاستلام عكس '.$money($reversalAmount).' فقط من أصل '.$money($cogsAmount).'. صافي التكلفة المحمّلة على الحسابات حالياً '.$money($net).' — الصحيح بعد الرفض صفر.',
            ];
        } else {
            $notes[] = [
                'level' => 'warning',
                'text' => 'الصحيح محاسبياً قيد خروج واحد للكمية المشحونة الحالية (صافي '.$money($net).' محمل الآن). عند التأجيل أو الرفض يجب عكس كل قيود التكلفة السابقة قبل أي شحن جديد.',
            ];
        }

        $stages[$cogsIndex]['notes'] = $notes;
        $stages[$cogsIndex]['anomaly'] = true;

        return $stages;
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     */
    private function stageIndex(array $stages, string $key): ?int
    {
        foreach ($stages as $index => $stage) {
            if (($stage['key'] ?? '') === $key) {
                return $index;
            }
        }

        return null;
    }

    private function shipmentCount(Order $order): int
    {
        if (! Schema::hasTable('order_shipments')) {
            return 0;
        }
        if ($order->relationLoaded('orderShipments')) {
            return $order->orderShipments->count();
        }

        return (int) $order->orderShipments()->count();
    }

    private function postponeCount(Order $order): int
    {
        $fromDetails = (int) ($order->order_details?->postponed ?? 0);
        if ($fromDetails > 0) {
            return $fromDetails;
        }
        if (! Schema::hasTable('trackings')) {
            return 0;
        }

        return (int) DB::table('trackings')
            ->where('order_id', $order->id)
            ->where('action', 'طلب مؤجل')
            ->count();
    }

    /**
     * @param  array<string, mixed>  $journal
     */
    private function isCogsReversalJournal(array $journal): bool
    {
        if ((string) ($journal['type'] ?? '') === 'cogs_reversal') {
            return true;
        }

        $text = trim((string) ($journal['description'] ?? '').' '.(string) ($journal['title'] ?? ''));

        return str_contains($text, 'عكس تكلفة')
            || str_contains($text, 'إرجاع تكلفة للمخزون');
    }

    /**
     * @param  list<array<string, mixed>>  $journals
     */
    private function firstJournalDate(array $journals): ?string
    {
        foreach ($journals as $journal) {
            $date = $this->dateOnly($journal['date'] ?? null);
            if ($date) {
                return $date;
            }
        }

        return null;
    }

    private function resolveDeliveryJournalDate(Order $order): ?Carbon
    {
        foreach ([
            $order->order_details?->delivery_date,
            $order->order_details?->canceled_date,
            $order->order_details?->shipping_date,
        ] as $raw) {
            $parsed = $this->parseDate($raw);
            if ($parsed) {
                return $parsed;
            }
        }

        return now()->startOfDay();
    }

    private function resolveReturnJournalDate(Order $order): ?Carbon
    {
        return $this->parseDate($order->order_details?->canceled_date)
            ?? $this->parseDate($order->order_details?->delivery_date)
            ?? now()->startOfDay();
    }

    private function resolveShippingReceivableAccountId(Order $order): ?int
    {
        $order->loadMissing(['order_details.shipping_company']);
        $raw = $order->order_details?->shipping_company?->receivable_tree_account_id;

        return app(ReceivableTreeAccountGuard::class)->sanitizeReceivableAccountId(
            $raw ? (int) $raw : null
        );
    }

    private function resolveCustomerAccount(Order $order): ?TreeAccount
    {
        return app(AccountLinkingService::class)->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null
        );
    }

    private function dateOnly(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
