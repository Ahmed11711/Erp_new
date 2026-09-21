<?php

namespace App\Services\Accounting;

use App\Enums\OrderCollectionStatus;
use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\SafeTransaction;
use App\Models\shippingCompanyDetails;
use App\Models\TreeAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * قيود مرحلة «تم التحصيل» للطلبات التي حُصِّلت تشغيلياً دون أن تُرحَّل قيودها.
 *
 * تحاكي بالضبط ما يُرحَّل عند تحويل الطلب إلى «تم التحصيل» من شاشة التحصيل
 * (OrdersController::collectShippingCompanyDetailLine):
 *  - قيد التحصيل (ORD-OPS-*): مدين مصدر النقد (بنك/خزينة) — دائن ذمة شركة الشحن/المندوب (أو العميل).
 *  - قيد تسوية الشحن (ORDER-SHIP-EXP-*): مدين مصروف شحن صادر — دائن ذمة شركة الشحن بقيمة الشحن.
 *
 * لا يحدّث الأرصدة التشغيلية (بنك/خزينة/شركة شحن) إن كانت الحركة الأصلية
 * مسجّلة بالفعل على نفس الحساب. إن اختار المستخدم حساب نقد مختلف (بنك محذوف
 * أو غير مرتبط) يُزامَن رصيد البنك/الخزينة المختارة بتاريخ القيد.
 */
class SalesOrderCollectionJournalService
{
    public const COLLECTION_BATCH_PREFIX = OrderPaymentSourceLedgerService::BATCH_PREFIX;

    public const LEGACY_COLLECTION_BATCH_PREFIX = 'COLLECT-';

    public const SHIPPING_EXPENSE_BATCH_PREFIX = 'ORDER-SHIP-EXP-';

    public const COLLECTED_STATUS = 'تم التحصيل';

    private const EXCLUDED_STATUSES = ['ملغي', 'أرشيف'];

    public function __construct(
        private LedgerJournalService $journal,
        private AccountLinkingService $accountLinking,
        private SalesOrderAccountingService $salesOrderAccounting,
    ) {
    }

    public function isCollected(Order $order): bool
    {
        $order->loadMissing('order_details');
        if (in_array((string) $order->order_status, self::EXCLUDED_STATUSES, true)) {
            return false;
        }
        if ((string) $order->order_status === self::COLLECTED_STATUS) {
            return true;
        }
        if (($order->order_details?->collection_status ?? null) === OrderCollectionStatus::Collected->value) {
            return true;
        }

        return $this->collectedShippingRows($order)->isNotEmpty();
    }

    public function hasCollectionJournal(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where(function ($q) use ($order) {
                $q->where('entry_batch_code', 'like', self::COLLECTION_BATCH_PREFIX.$order->id.'-%')
                    ->orWhere('entry_batch_code', 'like', self::LEGACY_COLLECTION_BATCH_PREFIX.$order->id.'-%');
            })
            ->exists();
    }

    public function hasShippingExpenseJournal(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', self::SHIPPING_EXPENSE_BATCH_PREFIX.$order->id.'-%')
            ->exists();
    }

    /**
     * مسودة قيد التحصيل (مدين بنك/خزينة — دائن ذمة شركة الشحن أو العميل).
     *
     * @return array<string, mixed>
     */
    public function previewCollection(Order $order): array
    {
        $order->loadMissing(['order_details.shipping_company']);
        $context = $this->composeContext($order);
        $base = [
            'applicable' => false,
            'posted' => $this->hasCollectionJournal($order),
            'can_post' => false,
            'reason' => null,
            'date' => $context['date'],
            'description' => $context['collection_description'],
            'total' => 0.0,
            'lines' => [],
            'sync_operational_default' => false,
            'cash_source_already_recorded' => false,
        ];

        if (! $this->isCollected($order)) {
            $base['reason'] = 'لم يُحصَّل الطلب بعد — قيد التحصيل يُرحَّل بتاريخ التحصيل.';

            return $base;
        }

        $base['applicable'] = true;
        if ($base['posted']) {
            $base['reason'] = 'قيد التحصيل موجود مسبقاً.';

            return $base;
        }

        if ($context['collected'] <= 0.009) {
            $base['applicable'] = false;
            $base['reason'] = 'لا يوجد مبلغ محصَّل لهذا الطلب.';

            return $base;
        }

        $amount = $context['collection_amount'];
        $base['total'] = $amount;
        if ($amount <= 0.009) {
            $base['applicable'] = false;
            $base['reason'] = 'كامل المبلغ المحصَّل يُسوَّى كمصروف شحن — لا يوجد نقد للتحصيل.';

            return $base;
        }

        if (! $context['credit_account_id']) {
            $base['reason'] = 'تعذر تحديد حساب الذمة (شركة الشحن أو العميل) لقيد التحصيل.';

            return $base;
        }

        $cashAccountId = $context['cash_account_id'];
        $lines = [
            [
                'account_id' => $cashAccountId ?? 0,
                'debit' => $amount,
                'credit' => 0.0,
                'description' => $context['collection_description'].' — تحصيل/إيداع',
            ],
            [
                'account_id' => (int) $context['credit_account_id'],
                'debit' => 0.0,
                'credit' => $amount,
                'description' => $context['collection_description'].' — تخفيض ذمة',
            ],
        ];

        $base['lines'] = $this->decorateLines($lines);
        $base['can_post'] = true;
        $alreadyRecorded = $cashAccountId !== null;
        $base['cash_source_already_recorded'] = $alreadyRecorded;
        $base['sync_operational_default'] = ! $alreadyRecorded;
        if (! $cashAccountId) {
            $base['reason'] = ($context['cash_source_hint'] ?? 'لم يُعثر على حركة بنك/خزينة مسجّلة لتحصيل هذا الطلب.')
                .' اختر حساب مصدر النقد (بنك/خزينة) في البند المدين قبل الترحيل.';
        } elseif ($context['cash_source_label']) {
            $base['reason'] = null;
            $base['cash_source'] = $context['cash_source_label'];
        }

        return $base;
    }

    /**
     * مسودة قيد تسوية الشحن (مدين مصروف شحن صادر — دائن ذمة شركة الشحن).
     *
     * @return array<string, mixed>
     */
    public function previewShippingExpense(Order $order): array
    {
        $order->loadMissing(['order_details.shipping_company']);
        $context = $this->composeContext($order);
        $base = [
            'applicable' => false,
            'posted' => $this->hasShippingExpenseJournal($order),
            'can_post' => false,
            'reason' => null,
            'date' => $context['date'],
            'description' => $context['shipping_description'],
            'total' => 0.0,
            'lines' => [],
        ];

        if (! $this->isCollected($order)) {
            $base['reason'] = 'لم يُحصَّل الطلب بعد — تسوية مصروف الشحن تُرحَّل مع التحصيل.';

            return $base;
        }

        if ($base['posted']) {
            $base['applicable'] = true;
            $base['reason'] = 'قيد تسوية مصروف الشحن موجود مسبقاً.';

            return $base;
        }

        if (! $context['can_net_shipping']) {
            $base['reason'] = $context['shipping_amount'] <= 0.009
                ? 'لا توجد قيمة شحن على هذا الطلب.'
                : 'شركة الشحن غير مربوطة بحساب ذمة — تُحصَّل قيمة الشحن نقداً ضمن قيد التحصيل.';

            return $base;
        }

        $base['applicable'] = true;
        $base['total'] = $context['shipping_amount'];

        // نفس سلوك شاشة التحصيل: حساب «مصروف شحن صادر» يُنشأ تلقائياً إن لم يكن موجوداً.
        try {
            $expenseAcc = TreeAccount::ensureFreightOutExpenseAccount();
        } catch (\Throwable $e) {
            $base['reason'] = $e->getMessage();

            return $base;
        }

        $lines = [
            [
                'account_id' => (int) $expenseAcc->id,
                'debit' => $context['shipping_amount'],
                'credit' => 0.0,
                'description' => $context['shipping_description'].' — مصروف شحن صادر',
            ],
            [
                'account_id' => (int) $context['shipping_receivable_account_id'],
                'debit' => 0.0,
                'credit' => $context['shipping_amount'],
                'description' => $context['shipping_description'].' — تخفيض ذمة التحصيل',
            ],
        ];
        $base['lines'] = $this->decorateLines($lines);
        $base['can_post'] = true;

        return $base;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function recordCollectionWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null,
        ?bool $forceSyncOperational = null
    ): string {
        if (! $this->isCollected($order)) {
            return 'skipped_ineligible';
        }
        if ($this->hasCollectionJournal($order)) {
            $this->syncOperationalFromPostedCollection($order, $forceSyncOperational === true);

            return 'skipped_exists';
        }

        $normalized = $this->normalizeLines($lines);
        if ($normalized === null) {
            return 'failed';
        }

        $context = $this->composeContext($order);
        $header = trim((string) ($description ?: $context['collection_description']));
        $batch = self::COLLECTION_BATCH_PREFIX.$order->id.'-'.$order->id.'-'.now()->format('YmdHis');
        $date = $journalDate ?? $this->parseDate($context['date']);
        $syncOperational = $forceSyncOperational === true
            || ($forceSyncOperational !== false && $this->shouldSyncOperationalForCollection($normalized, $context));

        try {
            $this->journal->postBalancedJournal(
                $normalized,
                $header,
                (int) $order->id,
                $batch,
                null,
                $date,
                $syncOperational
            );
        } catch (\Throwable $e) {
            Log::warning('SalesOrderCollectionJournal: collection posting failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        return $this->hasCollectionJournal($order) ? 'posted' : 'failed';
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function recordShippingExpenseWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null
    ): string {
        if (! $this->isCollected($order)) {
            return 'skipped_ineligible';
        }
        if ($this->hasShippingExpenseJournal($order)) {
            return 'skipped_exists';
        }

        $normalized = $this->normalizeLines($lines);
        if ($normalized === null) {
            return 'failed';
        }

        $context = $this->composeContext($order);
        $header = trim((string) ($description ?: $context['shipping_description']));
        $batch = self::SHIPPING_EXPENSE_BATCH_PREFIX.$order->id.'-'.now()->format('YmdHis');
        $date = $journalDate ?? $this->parseDate($context['date']);

        try {
            $this->journal->postBalancedJournal($normalized, $header, (int) $order->id, $batch, null, $date, false);
        } catch (\Throwable $e) {
            Log::warning('SalesOrderCollectionJournal: shipping expense posting failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }

        return $this->hasShippingExpenseJournal($order) ? 'posted' : 'failed';
    }

    /**
     * ترحيل قيود التحصيل الناقصة تلقائياً (بدون سطور مخصّصة).
     * قيد التحصيل يُرحَّل فقط إن أمكن تحديد مصدر النقد من الحركة التشغيلية المسجّلة.
     *
     * @return array{collection: string|null, shipping_expense: string|null, posted: list<string>}
     */
    public function postMissingForOrder(Order $order): array
    {
        $posted = [];
        $collection = null;
        $shippingExpense = null;

        if (! $this->isCollected($order)) {
            return ['collection' => null, 'shipping_expense' => null, 'posted' => []];
        }

        $collectionDraft = $this->previewCollection($order);
        if ($collectionDraft['posted']) {
            $this->syncOperationalFromPostedCollection($order);
            $collection = 'skipped_exists';
        } elseif ($collectionDraft['can_post'] && $this->linesHaveAccounts($collectionDraft['lines'])) {
            $collection = $this->recordCollectionWithLines($order, $collectionDraft['lines']);
            if ($collection === 'posted') {
                $posted[] = 'collection';
            }
        } else {
            $collection = 'skipped_ineligible';
        }

        $shippingDraft = $this->previewShippingExpense($order);
        if ($shippingDraft['posted']) {
            $shippingExpense = 'skipped_exists';
        } elseif ($shippingDraft['can_post']) {
            $shippingExpense = $this->recordShippingExpenseWithLines($order, $shippingDraft['lines']);
            if ($shippingExpense === 'posted') {
                $posted[] = 'shipping_expense';
            }
        } else {
            $shippingExpense = 'skipped_ineligible';
        }

        return [
            'collection' => $collection,
            'shipping_expense' => $shippingExpense,
            'posted' => $posted,
        ];
    }

    /**
     * يزامن الرصيد التشغيلي للبنك/الخزينة من قيد التحصيل المرحَّل إن كان حساب
     * النقد في الشجرة مختلفاً عن مصدر الحركة التشغيلية الأصلية (اختيار يدوي).
     */
    public function syncOperationalFromPostedCollection(Order $order, bool $force = false): bool
    {
        $context = $this->composeContext($order);
        $autoCash = (int) ($context['cash_account_id'] ?? 0);
        $entries = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where(function ($q) use ($order) {
                $q->where('entry_batch_code', 'like', self::COLLECTION_BATCH_PREFIX.$order->id.'-%')
                    ->orWhere('entry_batch_code', 'like', self::LEGACY_COLLECTION_BATCH_PREFIX.$order->id.'-%');
            })
            ->where('debit', '>', 0.009)
            ->get();

        if ($entries->isEmpty()) {
            return false;
        }

        $sync = app(PaymentSourceOperationalLedgerService::class);
        $did = false;
        foreach ($entries as $entry) {
            $accountId = (int) $entry->tree_account_id;
            if (! $force && $autoCash > 0 && $accountId === $autoCash) {
                continue;
            }
            $batch = (string) ($entry->entry_batch_code ?: (self::COLLECTION_BATCH_PREFIX.$order->id));
            if ($this->cashAccountHasOperationalRef($accountId, $batch)) {
                continue;
            }
            $date = $entry->created_at
                ? Carbon::parse((string) $entry->created_at)->toDateString()
                : $context['date'];
            try {
                $sync->syncFromJournalLine(
                    $accountId,
                    (float) $entry->debit,
                    (float) $entry->credit,
                    (string) $entry->description,
                    $batch,
                    $date
                );
                $did = true;
            } catch (\Throwable $e) {
                Log::warning('SalesOrderCollectionJournal: operational backfill failed', [
                    'order_id' => $order->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $did;
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, description: string}>  $normalized
     * @param  array<string, mixed>  $context
     */
    private function shouldSyncOperationalForCollection(array $normalized, array $context): bool
    {
        $autoCash = (int) ($context['cash_account_id'] ?? 0);
        $debitAccounts = [];
        foreach ($normalized as $line) {
            if (round((float) ($line['debit'] ?? 0), 2) > 0.009) {
                $debitAccounts[] = (int) $line['account_id'];
            }
        }
        if ($debitAccounts === []) {
            return false;
        }

        // المصدر التشغيلي الأصلي (بنك/خزينة) يحمل الحركة بالفعل — لا نضاعفها.
        return ! ($autoCash > 0 && in_array($autoCash, $debitAccounts, true));
    }

    private function cashAccountHasOperationalRef(int $treeAccountId, string $ref): bool
    {
        if ($treeAccountId < 1 || $ref === '') {
            return false;
        }

        $bank = app(BankOperationalLedgerService::class)->findByAssetId($treeAccountId);
        if ($bank) {
            return app(BankOperationalLedgerService::class)->hasOperationalDetailForRef($bank, $ref);
        }

        return false;
    }

    /**
     * يجمع كل ما يلزم لبناء قيدي التحصيل بنفس منطق شاشة التحصيل.
     *
     * @return array{
     *     collected: float,
     *     product_amount: float,
     *     shipping_amount: float,
     *     collection_amount: float,
     *     can_net_shipping: bool,
     *     shipping_receivable_account_id: int|null,
     *     credit_account_id: int|null,
     *     cash_account_id: int|null,
     *     cash_source_label: string|null,
     *     cash_source_hint: string|null,
     *     date: string,
     *     collection_description: string,
     *     shipping_description: string
     * }
     */
    private function composeContext(Order $order): array
    {
        $order->loadMissing(['order_details.shipping_company']);
        $od = $order->order_details;
        $shippingCo = $od?->shipping_company;

        $net = round((float) ($order->net_total ?? 0), 2);
        $prepaid = round(max(0, (float) ($order->prepaid_amount ?? 0)), 2);

        $rows = $this->collectedShippingRows($order);
        $collected = round((float) $rows->sum(fn ($row) => abs((float) $row->amount)), 2);
        if ($collected <= 0.009) {
            $collected = round(max(0, $net - $prepaid), 2);
        }
        $collected = round(min($collected, max($net, 0)), 2);

        // تقسيم: قيمة البضاعة نقداً + قيمة الشحن مقاصّة على ذمة الطرف الحامل للمديونية.
        $customerShipping = round(max(0, (float) ($order->shipping_cost ?? 0)), 2);
        $productAmount = round(max(0, $net - $customerShipping), 2);
        $productAmount = round(min($productAmount, $collected), 2);
        $shippingAmount = round(max(0, $collected - $productAmount), 2);

        $shippingReceivableAcc = app(ReceivableTreeAccountGuard::class)->sanitizeReceivableAccountId(
            $shippingCo?->receivable_tree_account_id ? (int) $shippingCo->receivable_tree_account_id : null
        );
        $canNetShipping = $shippingAmount > 0.009 && $shippingReceivableAcc !== null && $shippingCo !== null;
        $collectionAmount = $canNetShipping ? $productAmount : $collected;

        $creditAccountId = $shippingReceivableAcc ?? $this->resolveFallbackCreditAccountId($order);

        [$cashAccountId, $cashLabel, $cashDate, $cashHint] = $this->resolveCashSource($order);

        $date = $this->resolveCollectionDate($order, $rows, $cashDate);

        $partyName = $shippingCo?->name ?? ($shippingCo ? ('#'.$shippingCo->id) : 'شركة الشحن');
        $details = $shippingCo
            ? 'تحصيل من شركة شحن '.$partyName
            : 'تحصيل من العميل';
        if ($canNetShipping) {
            $details .= ' — قيمة البضاعة';
        }

        return [
            'collected' => $collected,
            'product_amount' => $productAmount,
            'shipping_amount' => $shippingAmount,
            'collection_amount' => round($collectionAmount, 2),
            'can_net_shipping' => $canNetShipping,
            'shipping_receivable_account_id' => $shippingReceivableAcc,
            'credit_account_id' => $creditAccountId,
            'cash_account_id' => $cashAccountId,
            'cash_source_label' => $cashLabel,
            'cash_source_hint' => $cashHint,
            'date' => $date,
            'collection_description' => $details.' — طلب رقم '.$order->id,
            'shipping_description' => 'تسوية شحن الطلب رقم '.$order->id.' — '.$partyName
                .' — إثبات مصروف شحن مقابل ذمة التحصيل',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, shippingCompanyDetails>
     */
    private function collectedShippingRows(Order $order)
    {
        return shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->where('status', self::COLLECTED_STATUS)
            ->where('amount', '<', 0)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * مصدر النقد من الحركة التشغيلية المسجّلة وقت التحصيل (بنك ثم خزينة).
     *
     * @return array{0: int|null, 1: string|null, 2: string|null, 3: string|null} [account_id, label, date, hint]
     */
    private function resolveCashSource(Order $order): array
    {
        $hint = null;

        if (Schema::hasTable('bank_details')) {
            $bankRow = DB::table('bank_details')
                ->where('ref', (string) $order->id)
                ->where('type', 'الطلبات')
                ->where('amount', '>', 0.009)
                ->where('details', 'like', '%تحصيل%')
                ->orderByDesc('id')
                ->first();
            if ($bankRow) {
                $bank = Bank::find($bankRow->bank_id);
                $assetId = (int) ($bank?->asset_id ?? 0);
                if ($assetId > 0) {
                    return [$assetId, 'بنك: '.($bank->name ?? ('#'.$bank->id)), $bankRow->date ?? null, null];
                }
                $hint = $bank
                    ? 'حركة التحصيل مسجّلة على بنك «'.$bank->name.'» بتاريخ '.($bankRow->date ?? '—').' لكنه غير مرتبط بحساب في الشجرة.'
                    : 'حركة التحصيل مسجّلة على بنك رقم #'.$bankRow->bank_id.' بتاريخ '.($bankRow->date ?? '—').' وهو غير موجود حالياً.';
            }
        }

        if (Schema::hasTable('safe_transactions')) {
            $safeRow = SafeTransaction::query()
                ->where('type', 'deposit')
                ->whereNotNull('to_safe_id')
                ->where('notes', 'like', '%(مرجع: '.$order->id.')%')
                ->orderByDesc('id')
                ->first();
            if ($safeRow) {
                $safe = Safe::find($safeRow->to_safe_id);
                $accountId = (int) ($safe?->account_id ?? 0);
                if ($accountId > 0) {
                    return [$accountId, 'خزينة: '.($safe->name ?? ('#'.$safe->id)), $safeRow->date ?? null, null];
                }
                $hint ??= 'حركة التحصيل مسجّلة على خزينة'.($safe ? ' «'.$safe->name.'»' : ' #'.$safeRow->to_safe_id).' غير مرتبطة بحساب في الشجرة.';
            }
        }

        return [null, null, null, $hint];
    }

    private function resolveFallbackCreditAccountId(Order $order): ?int
    {
        $fromPrepaid = $this->salesOrderAccounting->resolvePrepaidCreditTreeAccountId($order);
        if ($fromPrepaid) {
            return (int) $fromPrepaid;
        }

        $customer = $this->accountLinking->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null,
        );

        return $customer ? (int) $customer->id : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, shippingCompanyDetails>  $rows
     */
    private function resolveCollectionDate(Order $order, $rows, ?string $cashDate): string
    {
        foreach ([
            $rows->first()?->collect_date,
            $order->order_details?->collection_date,
            $cashDate,
        ] as $raw) {
            $parsed = $this->parseDate($raw);
            if ($parsed) {
                return $parsed->toDateString();
            }
        }

        return now()->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{account_id: int, debit: float, credit: float, description: string}>|null
     */
    private function normalizeLines(array $lines): ?array
    {
        $normalized = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $dr = round((float) ($line['debit'] ?? 0), 2);
            $cr = round((float) ($line['credit'] ?? 0), 2);
            if ($dr <= 0 && $cr <= 0) {
                continue;
            }
            if ($accountId < 1) {
                return null;
            }
            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $dr,
                'credit' => $cr,
                'description' => trim((string) ($line['description'] ?? '')),
            ];
            $debit += $dr;
            $credit += $cr;
        }
        if ($normalized === [] || abs($debit - $credit) > 0.009) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function linesHaveAccounts(array $lines): bool
    {
        foreach ($lines as $line) {
            if ((int) ($line['account_id'] ?? 0) < 1) {
                return false;
            }
        }

        return $lines !== [];
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, description: string}>  $lines
     * @return list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     */
    private function decorateLines(array $lines): array
    {
        $ids = array_values(array_filter(array_unique(array_map(fn ($line) => (int) $line['account_id'], $lines))));
        $accounts = $ids === []
            ? collect()
            : TreeAccount::query()->whereIn('id', $ids)->get(['id', 'code', 'name'])->keyBy('id');
        $out = [];
        foreach ($lines as $line) {
            $account = $accounts->get((int) $line['account_id']);
            $out[] = [
                'account_id' => (int) $line['account_id'],
                'account_code' => $account?->code !== null ? (string) $account->code : null,
                'account_name' => $account?->name !== null ? (string) $account->name : null,
                'debit' => round((float) $line['debit'], 2),
                'credit' => round((float) $line['credit'], 2),
                'description' => (string) $line['description'],
            ];
        }

        return $out;
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
