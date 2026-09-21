<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Double-entry accounting for sales orders.
 *
 * Invoice (order recognition):
 *   Dr  Receivables (Customer و/أو ذمم وسيط شحن/تحصيل حسب الإعداد)
 *   Cr  Sales revenue (بضاعة) أو Maintenance revenue (طلب صيانة) + Shipping revenue
 *
 * تقسيم الذمم (عند ربط حسابات أصول لشركة الشحن و/أو شركة التحصيل في order_details):
 *   - جزء الدفعة المقدمة prepayment → حساب ذمة شركة التحصيل (مثل Paymob) إن وُجد، وإلا ذمة العميل
 *   - الباقي (تحصيل عند التسليم) → ذمة شركة الشحن (مثل Bosta) إن وُجد، وإلا ذمة العميل
 *
 * Prepaid / advance on the same order (cash in):
 *   Dr  Cash/Bank/Safe
 *   Cr  نفس حساب الذمة المستخدم في الفاتورة للجزء المدفوع مقدماً (شركة تحصيل أو عميل)
 *
 * عند تسجيل دفعة مقدمة دون تحديد بنك/خزينة:
 *   Dr  مقبوضات بانتظار التسجيل
 *   Cr  ذمة العميل (أو وسيط التحصيل إن وُجد)
 * وعند ربط شركة تحصيل دون قيد نقدية (مثل Shopify) تبقى الذمة مفتوحة على الوسيط.
 *
 * Courier cost (ShippingCourierAccountingService) لا يمر على ذمم المبيعات.
 */
class SalesOrderAccountingService
{
    public const SHIPPED_PLUS_STATUSES = [
        'تم شحن',
        'شحن جزئي',
        'تم التسليم',
        'تسليم جزئي',
        'رفض استلام',
        'تم التحصيل',
        'تم الاستلام',
        'مرتجع',
    ];

    public const DELIVERED_PLUS_STATUSES = [
        'تم التسليم',
        'تسليم جزئي',
        'رفض استلام',
        'تم التحصيل',
        'تم الاستلام',
        'مرتجع',
    ];

    public function __construct(
        private LedgerJournalService $journal
    ) {
    }

    public function recordInitialOrderRecognition(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->writeEntries($order, skipPrepaid: false, journalDate: $this->resolveJournalDate($order));
        });
    }

    /**
     * عند إنشاء الطلب: السداد المقدم فقط.
     * إثبات المبيعات يُرحَّل بتاريخ الشحن.
     */
    public function recordPrepaidOnCreate(Order $order): void
    {
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return;
        }

        DB::transaction(function () use ($order) {
            $this->writePrepaidIfNeeded($order);
        });
    }

    /**
     * هل يوجد قيد إثبات فاتورة ORD-{id}-* لهذا الطلب؟
     * لا يشمل ORD-PREPAID / ORD-OPS / COGS / التسليم / التحصيل.
     */
    public function hasInvoiceRecognition(Order $order): bool
    {
        $pattern = 'ORD-' . $order->id . '-%';

        return AccountEntry::query()
            ->where('entry_batch_code', 'like', $pattern)
            ->exists();
    }

    public function hasPrepaidRecognition(Order $order): bool
    {
        $id = (int) $order->id;

        return AccountEntry::query()
            ->where('order_id', $id)
            ->where(function ($q) use ($id) {
                $q->where('entry_batch_code', 'like', 'ORD-PREPAID-'.$id.'-%')
                    ->orWhere('entry_batch_code', 'like', 'ORD-PREPAID-PENDING-'.$id.'-%')
                    ->orWhere('entry_batch_code', 'like', 'PARTCOLLECT-'.$id.'-%')
                    ->orWhere('entry_batch_code', 'like', 'ORD-OPS-'.$id.'-%');
            })
            ->exists();
    }

    /**
     * ترحيل قيد السداد المقدم إن وُجد مبلغ ولم يُرحَّل بعد.
     *
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'skipped_zero'|'failed'
     */
    public function recordPrepaidIfMissing(Order $order): string
    {
        if ((float) ($order->prepaid_amount ?? 0) <= 0.009) {
            return 'skipped_zero';
        }
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return 'skipped_ineligible';
        }
        if ($this->hasPrepaidRecognition($order)) {
            return 'skipped_exists';
        }

        $this->writePrepaidIfNeeded($order);

        return $this->hasPrepaidRecognition($order) ? 'posted' : 'failed';
    }

    /**
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
    public function previewPrepaid(Order $order): array
    {
        $order->loadMissing(['order_details.collection_company']);
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        $date = null;
        try {
            $date = $order->order_date ? Carbon::parse((string) $order->order_date)->toDateString() : now()->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        $base = [
            'applicable' => $prepaid > 0.009,
            'posted' => $this->hasPrepaidRecognition($order),
            'can_post' => false,
            'reason' => null,
            'date' => $date,
            'description' => 'تاريخ السداد المقدم — طلب رقم '.$order->id,
            'total' => $prepaid,
            'lines' => [],
        ];

        if (! $base['applicable']) {
            // بدون مبلغ مقدم لا يوجد قيد سداد مقدم — قيود التحصيل (ORD-OPS) لا تُحسب هنا.
            $base['posted'] = false;
            $base['reason'] = 'لا يوجد مبلغ تحت الحساب على هذا الطلب.';

            return $base;
        }
        if ($this->shouldSkipInvoiceRecognition($order)) {
            $base['reason'] = 'الطلب ملغي أو مؤرشف أو محوّل من عرض سعر.';

            return $base;
        }
        if ($base['posted']) {
            $base['reason'] = 'قيد السداد المقدم موجود مسبقاً.';

            return $base;
        }

        $composed = $this->composePrepaidDraftLines($order);
        if ($composed === null) {
            $base['reason'] = 'تعذر بناء قيد السداد المقدم: حساب العميل أو البنك/المقبوضات المعلّقة غير متوفر.';

            return $base;
        }

        $base['can_post'] = true;
        $base['description'] = $composed['description'];
        $base['lines'] = $this->decorateInvoiceLines($composed['lines']);

        return $base;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'skipped_zero'|'failed'
     */
    public function recordPrepaidWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null
    ): string {
        if ((float) ($order->prepaid_amount ?? 0) <= 0.009) {
            return 'skipped_zero';
        }
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return 'skipped_ineligible';
        }
        if ($this->hasPrepaidRecognition($order)) {
            return 'skipped_exists';
        }

        $normalized = $this->normalizePostedInvoiceLines($lines);
        if ($normalized === []) {
            return 'failed';
        }

        $header = trim((string) ($description ?: 'تاريخ السداد المقدم — طلب رقم '.$order->id));
        $looksPending = str_contains($header, 'بانتظار')
            || collect($normalized)->contains(fn ($line) => str_contains((string) ($line['description'] ?? ''), 'بانتظار'));
        $batch = ($looksPending ? 'ORD-PREPAID-PENDING-' : 'ORD-PREPAID-').$order->id.'-'.now()->format('YmdHis');
        $date = $journalDate ?? ($order->order_date ? Carbon::parse((string) $order->order_date)->startOfDay() : now()->startOfDay());

        $this->journal->postBalancedJournal($normalized, $header, $order->id, $batch, null, $date);

        return $this->hasPrepaidRecognition($order) ? 'posted' : 'failed';
    }

    /**
     * إعادة محاولة قيد إثبات الفاتورة فقط إن كان غائباً — نفس مسار إنشاء الطلب.
     * لا يحذف قيوداً موجودة، ولا يرحّل شحن/تسليم/تحصيل، ولا يغيّر شروط التخطي.
     *
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function recordInitialOrderRecognitionIfMissing(Order $order): string
    {
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return 'skipped_ineligible';
        }

        if ($this->hasInvoiceRecognition($order)) {
            return 'skipped_exists';
        }

        if (! $this->isReadyForInvoiceRecognition($order)) {
            return 'skipped_not_shipped';
        }

        $journalDate = $this->resolveJournalDate($order);

        DB::transaction(function () use ($order, $journalDate) {
            // skipPrepaid: لا ننشئ ORD-PREPAID هنا — الدفعة المقدمة مسار منفصل عند الإنشاء.
            $this->writeEntries($order, skipPrepaid: true, journalDate: $journalDate);
        });

        return $this->hasInvoiceRecognition($order) ? 'posted' : 'failed';
    }

    /**
     * معاينة قيد إثبات الفاتورة كما سيُرحَّل — بدون كتابة.
     *
     * @return array{
     *     order_id: int,
     *     customer_name: string|null,
     *     order_date: mixed,
     *     order_status: mixed,
     *     net_total: float,
     *     date: string,
     *     description: string,
     *     can_post: bool,
     *     reason: string|null,
     *     lines: list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>,
     *     total_debit: float,
     *     total_credit: float
     * }
     */
    public function previewInvoiceRecognition(Order $order): array
    {
        $order->loadMissing(['order_products', 'order_details.shipping_company', 'order_details.collection_company']);

        $journalDate = $this->resolveJournalDate($order);
        $base = [
            'order_id' => (int) $order->id,
            'customer_name' => $order->customer_name,
            'order_date' => $order->order_date,
            'order_status' => $order->order_status,
            'net_total' => round((float) ($order->net_total ?? 0), 2),
            'date' => $journalDate?->format('Y-m-d') ?? now()->toDateString(),
            'description' => $this->invoiceJournalDescription($order),
            'can_post' => false,
            'reason' => null,
            'lines' => [],
            'total_debit' => 0.0,
            'total_credit' => 0.0,
        ];

        if ($this->shouldSkipInvoiceRecognition($order)) {
            $base['reason'] = 'الطلب ملغي أو مؤرشف أو محوّل من عرض سعر — لا يُرحَّل قيد فاتورة.';

            return $base;
        }

        if ($this->hasInvoiceRecognition($order)) {
            $base['reason'] = 'قيد إثبات الفاتورة موجود مسبقاً.';

            return $base;
        }

        if (! $this->isReadyForInvoiceRecognition($order)) {
            $base['reason'] = 'لم يُشحن الطلب بعد — قيد المبيعات يُرحَّل بتاريخ الشحن.';

            return $base;
        }

        $composed = $this->composeInvoiceRecognitionLines($order);
        if ($composed === null) {
            $base['reason'] = 'تعذر بناء القيد: حساب العميل أو الإيرادات غير متوفر، أو صافي الفاتورة صفر.';

            return $base;
        }

        $lines = $this->decorateInvoiceLines($composed['lines']);
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);

        $base['can_post'] = true;
        $base['description'] = $composed['description'];
        $base['lines'] = $lines;
        $base['total_debit'] = $debit;
        $base['total_credit'] = $credit;

        return $base;
    }

    /**
     * ترحيل قيد إثبات الفاتورة بأسطر معدّلة من المستخدم.
     * لا يحذف قيوداً موجودة. يرفض إن كان القيد موجوداً أو الطلب غير مؤهل.
     *
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'skipped_ineligible'|'failed'
     */
    public function recordInvoiceRecognitionWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null
    ): string {
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return 'skipped_ineligible';
        }

        if ($this->hasInvoiceRecognition($order)) {
            return 'skipped_exists';
        }

        $normalized = $this->normalizePostedInvoiceLines($lines);
        if ($normalized === []) {
            return 'failed';
        }

        $header = trim((string) ($description ?: $this->invoiceJournalDescription($order)));
        if ($header === '') {
            $header = $this->invoiceJournalDescription($order);
        }

        $date = $journalDate ?? $this->resolveJournalDate($order);
        $batchCode = 'ORD-'.$order->id.'-'.now()->format('YmdHis');

        DB::transaction(function () use ($order, $normalized, $header, $batchCode, $date) {
            $this->journal->postBalancedJournal($normalized, $header, $order->id, $batchCode, null, $date);
        });

        return $this->hasInvoiceRecognition($order) ? 'posted' : 'failed';
    }

    /**
     * إعادة بناء قيود الفاتورة؛ واختيارياً إعادة بناء قيود الدفعة المقدمة بعد ربط شركات الشحن/التحصيل.
     *
     * @param  bool  $rebuildPrepaid  عند true يُحذف ORD-PREPAID-* ويُعاد إنشاؤه بمحاسبة وسيط التحصيل
     */
    public function refreshOrderRecognition(Order $order, bool $rebuildPrepaid = false): void
    {
        DB::transaction(function () use ($order, $rebuildPrepaid) {
            $hasInvoice = $this->hasInvoiceRecognition($order);
            $ready = $this->isReadyForInvoiceRecognition($order);
            $locked = $hasInvoice && $this->isInvoiceRefreshLocked($order);

            $pattern = 'ORD-' . $order->id . '-%';
            $affectedAccountIds = $this->collectAccountIdsFromOrderBatches($order->id, $pattern);

            if ($locked || (! $hasInvoice && ! $ready)) {
                if ($rebuildPrepaid) {
                    foreach ([
                        'ORD-PREPAID-' . $order->id . '-%',
                        'ORD-PREPAID-PENDING-' . $order->id . '-%',
                    ] as $prepaidPattern) {
                        $this->reverseOperationalSyncForDeletedBatches($order->id, $prepaidPattern);

                        $affectedAccountIds = array_values(array_unique(array_merge(
                            $affectedAccountIds,
                            $this->collectAccountIdsFromOrderBatches($order->id, $prepaidPattern)
                        )));

                        AccountEntry::where('order_id', $order->id)
                            ->where('entry_batch_code', 'like', $prepaidPattern)
                            ->delete();
                    }
                    $this->writePrepaidIfNeeded($order);
                }

                $this->rebuildTreeBalancesForAccounts($affectedAccountIds);

                return;
            }

            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', $pattern)
                ->delete();

            if ($rebuildPrepaid) {
                foreach ([
                    'ORD-PREPAID-' . $order->id . '-%',
                    'ORD-PREPAID-PENDING-' . $order->id . '-%',
                ] as $prepaidPattern) {
                    $this->reverseOperationalSyncForDeletedBatches($order->id, $prepaidPattern);

                    $affectedAccountIds = array_values(array_unique(array_merge(
                        $affectedAccountIds,
                        $this->collectAccountIdsFromOrderBatches($order->id, $prepaidPattern)
                    )));

                    AccountEntry::where('order_id', $order->id)
                        ->where('entry_batch_code', 'like', $prepaidPattern)
                        ->delete();
                }
            }

            $this->writeEntries($order, skipPrepaid: ! $rebuildPrepaid, journalDate: $this->resolveJournalDate($order));

            $this->rebuildTreeBalancesForAccounts($affectedAccountIds);
        });
    }

    /**
     * @return list<int>
     */
    private function collectAccountIdsFromOrderBatches(int $orderId, string $batchPattern): array
    {
        return AccountEntry::query()
            ->where('order_id', $orderId)
            ->where('entry_batch_code', 'like', $batchPattern)
            ->pluck('tree_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * بعد حذف قيود قديمة يجب إعادة تجميع أرصدة الشجرة وإلا تبقى ذمم العميل/الإيرادات معلّقة.
     *
     * @param  list<int>  $accountIds
     */
    private function rebuildTreeBalancesForAccounts(array $accountIds): void
    {
        if ($accountIds === []) {
            return;
        }

        $accounting = app(AccountingService::class);
        foreach ($accountIds as $accountId) {
            try {
                $accounting->updateAccountHierarchyBalances($accountId);
            } catch (\Throwable $e) {
                Log::warning('SalesOrderAccountingService: tree balance rebuild failed after entry removal', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * عكس الرصيد التشغيلي المرتبط بقيد محذوف (بنك/خزينة) قبل حذف account_entries.
     */
    private function reverseOperationalSyncForDeletedBatches(int $orderId, string $batchPattern): void
    {
        $batchCodes = AccountEntry::query()
            ->where('order_id', $orderId)
            ->where('entry_batch_code', 'like', $batchPattern)
            ->whereNotNull('entry_batch_code')
            ->pluck('entry_batch_code')
            ->unique()
            ->values();

        if ($batchCodes->isEmpty()) {
            return;
        }

        $bankLedger = app(BankOperationalLedgerService::class);
        $safeLedger = app(SafeOperationalLedgerService::class);

        foreach ($batchCodes as $batchCode) {
            $cashLines = AccountEntry::query()
                ->where('order_id', $orderId)
                ->where('entry_batch_code', $batchCode)
                ->where(function ($q) {
                    $q->where('debit', '>', 0.009)
                        ->orWhere('credit', '>', 0.009);
                })
                ->get(['tree_account_id', 'debit', 'credit']);

            foreach ($cashLines as $line) {
                $accountId = (int) $line->tree_account_id;
                $bank = $bankLedger->findByAssetId($accountId);
                if ($bank) {
                    $bankLedger->removeOperationalDetailsByRef($bank, (string) $batchCode);

                    continue;
                }

                $safe = $safeLedger->findByAccountId($accountId);
                if ($safe && abs((float) $line->debit - (float) $line->credit) > 0.009) {
                    $signed = -round((float) $line->debit - (float) $line->credit, 2);
                    $safeLedger->recordOperationalMovement(
                        $safe,
                        $signed,
                        'عكس قيد محذوف — ' . $batchCode,
                        (string) $batchCode . '-REV',
                        'قيود',
                        null,
                        date('Y-m-d')
                    );
                }
            }
        }
    }

    public function shouldSkipInvoiceRecognition(Order $order): bool
    {
        if (! empty($order->offer_debt_posted)) {
            Log::info('SalesOrderAccountingService: skipped ORD invoice recognition for offer-converted order', [
                'order_id' => $order->id,
                'offer_id' => $order->offer_id,
            ]);

            return true;
        }

        if (in_array((string) $order->order_status, ['ملغي', 'أرشيف'], true)) {
            Log::info('SalesOrderAccountingService: skipped ORD invoice recognition for cancelled/archived order', [
                'order_id' => $order->id,
                'order_status' => $order->order_status,
            ]);

            return true;
        }

        return false;
    }

    public function isReadyForInvoiceRecognition(Order $order): bool
    {
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return false;
        }

        if (in_array((string) $order->order_status, self::SHIPPED_PLUS_STATUSES, true)) {
            return true;
        }

        $order->loadMissing('order_details');

        return ! empty($order->order_details?->shipping_date);
    }

    public function isInvoiceRefreshLocked(Order $order): bool
    {
        if (in_array((string) $order->order_status, self::DELIVERED_PLUS_STATUSES, true)) {
            return true;
        }

        $order->loadMissing('order_details');
        if (! empty($order->order_details?->delivery_date)) {
            return true;
        }

        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-'.$order->id.'-%')
            ->exists();
    }

    /**
     * @return array{description: string, lines: list<array{account_id: int, debit: float, credit: float, description: string}>}|null
     */
    public function composeInvoiceRecognitionLines(Order $order): ?array
    {
        $customerAccount = $this->resolveCustomerAccount($order);
        if (! $customerAccount) {
            Log::warning('SalesOrderAccountingService: cannot resolve customer account', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        $isMaintenance = $this->isMaintenanceOrder($order);
        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        $shippingRevenueAcc = TreeAccount::resolveShippingRevenueAccount();
        $maintenanceAcc = $isMaintenance ? TreeAccount::resolveMaintenanceRevenueAccount() : null;
        $serviceAcc = $isMaintenance ? ($maintenanceAcc ?: $salesAcc) : $salesAcc;

        if (! $serviceAcc) {
            Log::warning('SalesOrderAccountingService: missing revenue account', [
                'order_id' => $order->id,
                'order_type' => $order->order_type,
                'maintenance' => $isMaintenance,
            ]);

            return null;
        }

        if ($isMaintenance && ! $maintenanceAcc) {
            Log::warning('SalesOrderAccountingService: maintenance revenue account missing; falling back to sales', [
                'order_id' => $order->id,
            ]);
        }

        $productTotal = $this->resolveProductTotal($order, $isMaintenance);
        $shippingRevenue = (float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0);
        $discount = (float) ($order->discount ?? 0);

        $netProductSales = max(0, $productTotal - $discount);
        $grandTotal = $netProductSales + $shippingRevenue;

        if ($grandTotal <= 0) {
            return null;
        }

        $receivableDebits = $this->buildSplitReceivableDebits($order, $customerAccount, $grandTotal);
        $lines = [];
        foreach ($receivableDebits as $row) {
            $lines[] = [
                'account_id' => (int) $row['account_id'],
                'debit' => round((float) $row['amount'], 2),
                'credit' => 0.0,
                'description' => (string) $row['description'],
            ];
        }

        if ($netProductSales > 0) {
            $usedMaintenance = $isMaintenance && $maintenanceAcc;
            $fallbackSales = $isMaintenance && ! $maintenanceAcc;
            $lines[] = [
                'account_id' => (int) $serviceAcc->id,
                'debit' => 0.0,
                'credit' => round($netProductSales, 2),
                'description' => $usedMaintenance
                    ? 'إيرادات الصيانة'
                    : ($fallbackSales
                        ? 'إيرادات الصيانة (لم يُعثر على حساب إيراد صيانة منفصل)'
                        : 'إيرادات المبيعات (بضاعة)'),
            ];
        }

        $shippingFallbackAcc = $salesAcc ?: $serviceAcc;
        if ($shippingRevenue > 0 && $shippingRevenueAcc) {
            $lines[] = [
                'account_id' => (int) $shippingRevenueAcc->id,
                'debit' => 0.0,
                'credit' => round($shippingRevenue, 2),
                'description' => 'إيراد شحن وتوصيل محصل من العميل',
            ];
        } elseif ($shippingRevenue > 0 && $shippingFallbackAcc) {
            $lines[] = [
                'account_id' => (int) $shippingFallbackAcc->id,
                'debit' => 0.0,
                'credit' => round($shippingRevenue, 2),
                'description' => 'إيراد شحن (لم يُعثر على حساب إيراد شحن منفصل)',
            ];
        }

        if ($lines === []) {
            return null;
        }

        return [
            'description' => $this->invoiceJournalDescription($order),
            'lines' => $lines,
        ];
    }

    private function isMaintenanceOrder(Order $order): bool
    {
        return trim((string) $order->order_type) === 'طلب صيانة';
    }

    private function invoiceJournalDescription(Order $order): string
    {
        $kind = $this->isMaintenanceOrder($order) ? 'فاتورة صيانة' : 'فاتورة مبيعات';

        return $kind.' — طلب رقم '.$order->id;
    }

    private function resolveJournalDate(Order $order): ?Carbon
    {
        $order->loadMissing('order_details');
        foreach ([
            $order->order_details?->shipping_date,
            $order->order_date,
        ] as $raw) {
            if (empty($raw)) {
                continue;
            }
            try {
                return Carbon::parse((string) $raw)->startOfDay();
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, description: string}>  $lines
     * @return list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     */
    private function decorateInvoiceLines(array $lines): array
    {
        $ids = array_values(array_unique(array_map(fn ($line) => (int) $line['account_id'], $lines)));
        $accounts = TreeAccount::query()
            ->whereIn('id', $ids)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

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

    /**
     * @param  list<array{account_id?: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return list<array{account_id: int, debit: float, credit: float, description: string}>
     */
    private function normalizePostedInvoiceLines(array $lines): array
    {
        if (count($lines) > 20) {
            throw new \InvalidArgumentException('عدد بنود القيد أكبر من المسموح (20).');
        }

        $normalized = [];
        $accountIds = [];
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($accountId < 1) {
                throw new \InvalidArgumentException('كل بند يحتاج حساباً من الشجرة.');
            }
            if ($debit < 0 || $credit < 0) {
                throw new \InvalidArgumentException('المبالغ لا يمكن أن تكون سالبة.');
            }
            if ($debit > 0 && $credit > 0) {
                throw new \InvalidArgumentException('البند الواحد لا يكون مديناً ودائناً معاً.');
            }
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $accountIds[$accountId] = true;
            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => trim((string) ($line['description'] ?? '')),
            ];
        }

        if ($normalized === []) {
            return [];
        }

        $found = TreeAccount::query()
            ->whereIn('id', array_keys($accountIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $missing = array_diff(array_keys($accountIds), $found);
        if ($missing !== []) {
            throw new \InvalidArgumentException('حساب غير موجود في الشجرة: '.implode(', ', $missing));
        }

        $debit = round(array_sum(array_column($normalized, 'debit')), 2);
        $credit = round(array_sum(array_column($normalized, 'credit')), 2);
        if (abs($debit - $credit) > 0.009) {
            throw new \InvalidArgumentException("القيد غير متوازن: مدين {$debit} ≠ دائن {$credit}.");
        }

        return $normalized;
    }

    private function writeEntries(Order $order, bool $skipPrepaid = false, ?\DateTimeInterface $journalDate = null): void
    {
        // طلبات محوّلة من عروض أسعار: المديونية وإيراد المبيعات رُحّلا مسبقاً على العرض
        // (OfferDebtAccountingService بدفعة OFFER-*). لا نكرّر قيد ORD-* هنا وإلا تُضاعف
        // ذمة العميل والإيراد في الشجرة.
        if ($this->shouldSkipInvoiceRecognition($order)) {
            return;
        }

        $composed = $this->composeInvoiceRecognitionLines($order);
        if ($composed === null) {
            return;
        }

        $batchCode = 'ORD-' . $order->id . '-' . now()->format('YmdHis');
        $this->journal->postBalancedJournal(
            $composed['lines'],
            $composed['description'],
            $order->id,
            $batchCode,
            null,
            $journalDate
        );

        if ($skipPrepaid) {
            return;
        }

        $this->writePrepaidIfNeeded($order);
    }

    /**
     * @return array{description: string, lines: list<array{account_id: int, debit: float, credit: float, description: string}>}|null
     */
    private function composePrepaidDraftLines(Order $order): ?array
    {
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        $customerAccount = $this->resolveCustomerAccount($order);
        if ($prepaid <= 0.009 || ! $customerAccount) {
            return null;
        }

        $creditReceivableId = $this->resolvePrepaidCreditTreeAccountId($order) ?? $customerAccount->id;
        $cashAccountId = $this->resolveCashAccountIdForPrepaid($order);

        if ($cashAccountId) {
            return [
                'description' => 'دفعة مقدمة — طلب رقم '.$order->id,
                'lines' => [
                    [
                        'account_id' => $cashAccountId,
                        'debit' => $prepaid,
                        'credit' => 0.0,
                        'description' => 'تحصيل دفعة مقدمة — نقدية/بنك',
                    ],
                    [
                        'account_id' => $creditReceivableId,
                        'debit' => 0.0,
                        'credit' => $prepaid,
                        'description' => 'تخفيض ذمة (عميل/وسيط تحصيل) — دفعة مقدمة',
                    ],
                ],
            ];
        }

        if ($this->shouldKeepPrepaidReceivableOpen($order, $creditReceivableId, $customerAccount)
            && $creditReceivableId !== (int) $customerAccount->id
        ) {
            return [
                'description' => 'تاريخ السداد المقدم — طلب رقم '.$order->id,
                'lines' => [
                    [
                        'account_id' => $creditReceivableId,
                        'debit' => $prepaid,
                        'credit' => 0.0,
                        'description' => 'ذمة شركة التحصيل — سداد مقدم',
                    ],
                    [
                        'account_id' => (int) $customerAccount->id,
                        'debit' => 0.0,
                        'credit' => $prepaid,
                        'description' => 'تخفيض ذمة العميل — سداد مقدم',
                    ],
                ],
            ];
        }

        try {
            $pendingAccount = TreeAccount::ensureUnallocatedPrepaidReceiptsAccount();
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'description' => 'دفعة مقدمة بانتظار تسجيل مصدر الدفع — طلب رقم '.$order->id,
            'lines' => [
                [
                    'account_id' => (int) $pendingAccount->id,
                    'debit' => $prepaid,
                    'credit' => 0.0,
                    'description' => 'مقبوضات عملاء بانتظار تسجيل بنك/خزينة',
                ],
                [
                    'account_id' => $creditReceivableId,
                    'debit' => 0.0,
                    'credit' => $prepaid,
                    'description' => 'تخفيض ذمة العميل — مبلغ تحت الحساب',
                ],
            ],
        ];
    }

    private function writePrepaidIfNeeded(Order $order): void
    {
        if ((float) ($order->prepaid_amount ?? 0) <= 0.009) {
            return;
        }

        $paymentType = trim((string) ($order->prepaid_payment_type ?? ''));

        if (in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            return;
        }

        if (in_array($paymentType, ['', 'pending', 'none', 'collection_company'], true)) {
            $customerAccount = $this->resolveCustomerAccount($order);
            if ($customerAccount) {
                $this->postPrepaidCollectionEntry($order, $customerAccount);
            }
        }
    }

    /**
     * مدين إثبات المبيعات على العميل فقط.
     * نقل الذمة لشركة الشحن يتم بتاريخ التسليم، والسداد المقدم قيد منفصل.
     *
     * @return array<int, array{account_id: int, amount: float, description: string}>
     */
    private function buildSplitReceivableDebits(Order $order, TreeAccount $customerAccount, float $grandTotal): array
    {
        return [[
            'account_id' => (int) $customerAccount->id,
            'amount' => round($grandTotal, 2),
            'description' => 'ذمم العميل — إجمالي الفاتورة',
        ]];
    }

    private function postPrepaidCollectionEntry(Order $order, TreeAccount $customerAccount): void
    {
        $prepaid = (float) $order->prepaid_amount;
        $creditReceivableId = $this->resolvePrepaidCreditTreeAccountId($order) ?? $customerAccount->id;
        $cashAccountId = $this->resolveCashAccountIdForPrepaid($order);

        if ($cashAccountId) {
            $prepaidBatch = 'ORD-PREPAID-' . $order->id . '-' . now()->format('YmdHis');
            $prepaidDesc = 'دفعة مقدمة — طلب رقم ' . $order->id;

            $this->journal->postBalancedJournal(
                [
                    [
                        'account_id' => $cashAccountId,
                        'debit' => $prepaid,
                        'credit' => 0,
                        'description' => 'تحصيل دفعة مقدمة — نقدية/بنك',
                    ],
                    [
                        'account_id' => $creditReceivableId,
                        'debit' => 0,
                        'credit' => $prepaid,
                        'description' => 'تخفيض ذمة (عميل/وسيط تحصيل) — دفعة مقدمة',
                    ],
                ],
                $prepaidDesc,
                $order->id,
                $prepaidBatch,
                null,
                null,
                true
            );

            return;
        }

        if ($this->shouldKeepPrepaidReceivableOpen($order, $creditReceivableId, $customerAccount)) {
            $this->postAdvanceCollectionCompanyEntry($order, $customerAccount, $creditReceivableId);

            return;
        }

        try {
            $pendingAccount = TreeAccount::ensureUnallocatedPrepaidReceiptsAccount();
        } catch (\Throwable $e) {
            Log::warning('SalesOrderAccountingService: prepaid pending account unavailable', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $prepaidBatch = 'ORD-PREPAID-PENDING-' . $order->id . '-' . now()->format('YmdHis');
        $prepaidDesc = 'دفعة مقدمة بانتظار تسجيل مصدر الدفع — طلب رقم ' . $order->id;

        $this->journal->postBalancedJournal(
            [
                [
                    'account_id' => $pendingAccount->id,
                    'debit' => $prepaid,
                    'credit' => 0,
                    'description' => 'مقبوضات عملاء بانتظار تسجيل بنك/خزينة',
                ],
                [
                    'account_id' => $creditReceivableId,
                    'debit' => 0,
                    'credit' => $prepaid,
                    'description' => 'تخفيض ذمة العميل — مبلغ تحت الحساب',
                ],
            ],
            $prepaidDesc,
            $order->id,
            $prepaidBatch
        );
    }

    /**
     * تاريخ السداد المقدم عبر شركة التحصيل:
     * من حـ/ شركة التحصيل  إلى حـ/ العميل
     */
    private function postAdvanceCollectionCompanyEntry(
        Order $order,
        TreeAccount $customerAccount,
        int $collectionAccountId
    ): void {
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        if ($prepaid <= 0.009 || $collectionAccountId === (int) $customerAccount->id) {
            return;
        }

        $alreadyOnCollection = (float) AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('tree_account_id', $collectionAccountId)
            ->sum('debit');
        if ($alreadyOnCollection > 0.009) {
            Log::info('SalesOrderAccountingService: collection advance already posted', [
                'order_id' => $order->id,
                'receivable_account_id' => $collectionAccountId,
            ]);

            return;
        }

        $prepaidBatch = 'ORD-PREPAID-'.$order->id.'-'.now()->format('YmdHis');
        $this->journal->postBalancedJournal(
            [
                [
                    'account_id' => $collectionAccountId,
                    'debit' => $prepaid,
                    'credit' => 0,
                    'description' => 'ذمة شركة التحصيل — سداد مقدم',
                ],
                [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0,
                    'credit' => $prepaid,
                    'description' => 'تخفيض ذمة العميل — سداد مقدم',
                ],
            ],
            'تاريخ السداد المقدم — طلب رقم '.$order->id,
            $order->id,
            $prepaidBatch
        );
    }

    /**
     * شركة تحصيل/وسيط يحتفظ بالنقدية — لا قيد بنك حتى التسوية (نفس منطق Shopify).
     */
    private function shouldKeepPrepaidReceivableOpen(Order $order, int $creditReceivableId, TreeAccount $customerAccount): bool
    {
        if ($creditReceivableId !== (int) $customerAccount->id) {
            return true;
        }

        $order->loadMissing(['order_details.collection_company']);

        return app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrder($order) !== null;
    }

    /**
     * حساب ذمة الدفعة المقدمة: شركة التحصيل إن وُجد ربط وحساب أصول، وإلا عميل الطلب.
     */
    public function resolvePrepaidCreditTreeAccountId(Order $order): ?int
    {
        $order->loadMissing(['order_details.collection_company']);

        $resolved = app(CollectionReceivableAccountResolver::class)->receivableAccountIdForOrder($order);
        if ($resolved) {
            return $resolved;
        }

        $cust = $this->resolveCustomerAccount($order);

        return $cust?->id;
    }

    /**
     * Product/service subtotal before discount: from order lines when present; otherwise total_invoice − shipping.
     * طلبات الصيانة: أصناف الطلب غالباً بإجمالي صفر (تتبع فقط)، والمبلغ المحصّل في رأس الفاتورة
     * (تكلفة الصيانة ± الشحن). نعتمد رأس الفاتورة حتى لا يُرحَّل الإيراد على مبيعات البضاعة.
     */
    private function resolveProductTotal(Order $order, ?bool $isMaintenance = null): float
    {
        $isMaintenance ??= $this->isMaintenanceOrder($order);
        $shipping = (float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0);
        $fromInvoice = round(max(0, (float) $order->total_invoice - $shipping), 2);

        if ($isMaintenance) {
            return $fromInvoice;
        }

        $lineTotal = (float) $order->order_products()
            ->sum(DB::raw('COALESCE(total_price, quantity * price)'));

        if ($lineTotal > 0) {
            return round($lineTotal, 2);
        }

        return $fromInvoice;
    }

    private function resolveCustomerAccount(Order $order): ?TreeAccount
    {
        $accountLinkingService = app(AccountLinkingService::class);

        return $accountLinkingService->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null
        );
    }

    private function resolveBankAccountId(?int $bankId): ?int
    {
        if (!$bankId) {
            return null;
        }

        $bank = Bank::find($bankId);

        return $bank && $bank->asset_id ? (int) $bank->asset_id : null;
    }

    /**
     * Resolves tree account for money-in on order create/update (bank, safe, or service account).
     * Uses the current HTTP request when order.bank_id is empty (e.g. safe collection).
     */
    public function resolveCashTreeAccountIdForOrder(Order $order): ?int
    {
        return $this->resolveCashAccountIdForPrepaid($order);
    }

    private function resolveCashAccountIdForPrepaid(Order $order): ?int
    {
        $storedType = trim((string) ($order->prepaid_payment_type ?? ''));
        if (in_array($storedType, ['pending', 'collection_company'], true)) {
            return null;
        }

        $paymentType = $storedType !== ''
            ? $storedType
            : request()->input('payment_type', 'bank');

        if (in_array($paymentType, ['pending', 'collection_company'], true)) {
            return null;
        }

        if ($paymentType === 'safe') {
            $safeId = request()->input('safe_id');
            if ($safeId) {
                $safe = Safe::find($safeId);

                return $safe?->account_id ? (int) $safe->account_id : null;
            }
        }

        if ($paymentType === 'service_account') {
            $svcId = request()->input('service_account_id');
            if ($svcId) {
                $svc = ServiceAccount::find($svcId);

                return $svc?->account_id ? (int) $svc->account_id : null;
            }
        }

        $bankId = $order->bank_id;
        if (!$bankId) {
            $bankId = request()->input('bank') ?? request()->input('bank_id');
        }
        if ($bankId !== null && $bankId !== '' && $bankId !== 'null') {
            return $this->resolveBankAccountId((int) $bankId);
        }

        return null;
    }
}
