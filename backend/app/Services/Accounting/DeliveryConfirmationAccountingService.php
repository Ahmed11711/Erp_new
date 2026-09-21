<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * عند تأكيد تسليم الطلب (تم التسليم) تنتقل المديونية من العميل إلى المندوب/شركة الشحن:
 *
 *   من ح/ المندوب أو شركة الشحن   (Dr)
 *   إلى ح/ العميل                  (Cr)
 *
 * في حالة السداد المقدم (prepaid) عبر شركة تحصيل:
 *   من ح/ شركة التحصيل             (Dr)
 *   إلى ح/ العميل                  (Cr)
 *
 * المبلغ المتبقي (COD) ينتقل إلى شركة الشحن/المندوب.
 */
class DeliveryConfirmationAccountingService
{
    public function __construct(
        private LedgerJournalService $journal
    ) {
    }

    /**
     * @return array{batch_code: string, transferred_amount: float}
     */
    public function recordDeliveryReceivableTransfer(Order $order, ?\DateTimeInterface $journalDate = null): array
    {
        $order->loadMissing([
            'order_details.shipping_company',
            'order_details.collection_company',
        ]);

        $od = $order->order_details;
        if (!$od) {
            throw new \RuntimeException('الطلب ليس له تفاصيل شحن.');
        }

        $customerAccount = $this->resolveCustomerAccount($order);
        if (!$customerAccount) {
            throw new \RuntimeException('تعذر تحديد حساب العميل للطلب رقم ' . $order->id);
        }

        $grandTotal = (float) $order->net_total;
        if ($grandTotal <= 0) {
            return ['batch_code' => '', 'transferred_amount' => 0];
        }

        $prepaid = (float) ($order->prepaid_amount ?? 0);

        $shippingCo = $od->shipping_company;
        $collectionCo = $od->collection_company;

        $shippingReceivableAcc = app(ReceivableTreeAccountGuard::class)->sanitizeReceivableAccountId(
            $shippingCo?->receivable_tree_account_id ? (int) $shippingCo->receivable_tree_account_id : null
        );
        $collectionReceivableAcc = app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);

        $prepaidPart = round(min($prepaid, $grandTotal), 2);
        $codPart = round(max(0, $grandTotal - $prepaidPart), 2);
        if ($prepaid > $grandTotal + 0.02) {
            $prepaidPart = round($prepaid, 2);
            $codPart = round($grandTotal, 2);
        }

        if ($this->hasUsableStoredSplit($od)) {
            $codPart = round((float) $od->shipping_receivable_amount, 2);
            $prepaidPart = round((float) $od->collection_receivable_amount, 2);
        }

        $alreadyOnShipping = $this->receivableAlreadyOnAccount($order->id, $shippingReceivableAcc);
        $alreadyOnCollection = $this->receivableAlreadyOnAccount($order->id, $collectionReceivableAcc);

        $codToTransfer = round(max(0, $codPart - $alreadyOnShipping), 2);
        $prepaidToTransfer = round(max(0, $prepaidPart - $alreadyOnCollection), 2);

        $batchCode = 'DELIVERY-' . $order->id . '-' . now()->format('YmdHis');
        $desc = 'تأكيد تسليم — نقل ذمة العميل إلى المندوب/شركة الشحن — طلب رقم ' . $order->id;

        $lines = $this->buildTransferLines(
            $customerAccount,
            $shippingCo,
            $collectionCo,
            $shippingReceivableAcc,
            $collectionReceivableAcc,
            $codToTransfer,
            $prepaidToTransfer
        );

        if (empty($lines)) {
            Log::info('DeliveryConfirmation: no receivable transfer needed (already on intermediary or no intermediary accounts)', [
                'order_id' => $order->id,
                'already_on_shipping' => $alreadyOnShipping,
                'already_on_collection' => $alreadyOnCollection,
            ]);
            return ['batch_code' => $batchCode, 'transferred_amount' => $grandTotal];
        }

        $date = $journalDate;
        if ($date === null && ! empty($od->delivery_date)) {
            try {
                $date = \Carbon\Carbon::parse((string) $od->delivery_date)->startOfDay();
            } catch (\Throwable $e) {
                $date = null;
            }
        }

        return DB::transaction(function () use ($lines, $desc, $order, $batchCode, $grandTotal, $date) {
            $this->journal->postBalancedJournal($lines, $desc, $order->id, $batchCode, null, $date);

            return ['batch_code' => $batchCode, 'transferred_amount' => $grandTotal];
        });
    }

    public function hasDeliveryBatch(Order $order): bool
    {
        return AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', 'DELIVERY-'.$order->id.'-%')
            ->exists();
    }

    /**
     * مسودة قيد نقل الذمة عند التسليم — بدون كتابة.
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
    public function composeTransferDraft(Order $order): array
    {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);
        $od = $order->order_details;
        $date = now()->toDateString();
        if (! empty($od?->delivery_date)) {
            try {
                $date = \Carbon\Carbon::parse((string) $od->delivery_date)->toDateString();
            } catch (\Throwable $e) {
            }
        }
        $description = 'تأكيد تسليم — نقل ذمة العميل إلى المندوب/شركة الشحن — طلب رقم '.$order->id;
        $base = [
            'applicable' => false,
            'posted' => $this->hasDeliveryBatch($order),
            'can_post' => false,
            'reason' => null,
            'date' => $date,
            'description' => $description,
            'total' => 0.0,
            'lines' => [],
        ];

        if (! $od) {
            $base['reason'] = 'الطلب ليس له تفاصيل شحن.';

            return $base;
        }

        $customerAccount = $this->resolveCustomerAccount($order);
        if (! $customerAccount) {
            $base['reason'] = 'تعذر تحديد حساب العميل لنقل الذمة عند التسليم.';

            return $base;
        }

        $grandTotal = (float) ($order->net_total ?? 0);
        if ($grandTotal <= 0.009) {
            $base['reason'] = 'لا يوجد مبلغ لنقل الذمة عند التسليم.';

            return $base;
        }

        $prepaid = (float) ($order->prepaid_amount ?? 0);
        $shippingCo = $od->shipping_company;
        $collectionCo = $od->collection_company;
        $shippingReceivableAcc = app(ReceivableTreeAccountGuard::class)->sanitizeReceivableAccountId(
            $shippingCo?->receivable_tree_account_id ? (int) $shippingCo->receivable_tree_account_id : null
        );
        $collectionReceivableAcc = app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);

        $prepaidPart = round(min($prepaid, $grandTotal), 2);
        $codPart = round(max(0, $grandTotal - $prepaidPart), 2);
        if ($prepaid > $grandTotal + 0.02) {
            $prepaidPart = round($prepaid, 2);
            $codPart = round($grandTotal, 2);
        }
        if ($this->hasUsableStoredSplit($od)) {
            $codPart = round((float) $od->shipping_receivable_amount, 2);
            $prepaidPart = round((float) $od->collection_receivable_amount, 2);
        }

        $codToTransfer = round(max(0, $codPart - $this->receivableAlreadyOnAccount($order->id, $shippingReceivableAcc)), 2);
        $prepaidToTransfer = round(max(0, $prepaidPart - $this->receivableAlreadyOnAccount($order->id, $collectionReceivableAcc)), 2);
        $lines = $this->buildTransferLines(
            $customerAccount,
            $shippingCo,
            $collectionCo,
            $shippingReceivableAcc,
            $collectionReceivableAcc,
            $codToTransfer,
            $prepaidToTransfer
        );
        $total = round(array_sum(array_column($lines, 'debit')), 2);
        $base['total'] = $total;
        if ($lines === []) {
            if (! $shippingReceivableAcc && $codToTransfer > 0.009) {
                $base['reason'] = 'حساب ذمة شركة الشحن/المندوب غير مربوط — لا يمكن ترحيل قيد التسليم.';
            } else {
                $base['reason'] = 'لا يوجد مبلغ متبقي لنقله إلى شركة الشحن عند التسليم.';
            }

            return $base;
        }

        $base['can_post'] = true;
        $base['lines'] = $this->decorateLines($lines);

        return $base;
    }

    /**
     * @param  list<array{account_id: int, debit?: float|int, credit?: float|int, description?: string}>  $lines
     * @return 'posted'|'skipped_exists'|'failed'
     */
    public function recordTransferWithLines(
        Order $order,
        array $lines,
        ?string $description = null,
        ?\DateTimeInterface $journalDate = null
    ): string {
        if ($this->hasDeliveryBatch($order)) {
            return 'skipped_exists';
        }

        $normalized = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $dr = round((float) ($line['debit'] ?? 0), 2);
            $cr = round((float) ($line['credit'] ?? 0), 2);
            if ($accountId < 1 || ($dr <= 0 && $cr <= 0)) {
                continue;
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
            return 'failed';
        }

        $order->loadMissing('order_details');
        $header = trim((string) ($description ?: 'تأكيد تسليم — نقل ذمة العميل إلى المندوب/شركة الشحن — طلب رقم '.$order->id));
        $batch = 'DELIVERY-'.$order->id.'-'.now()->format('YmdHis');
        $date = $journalDate;
        if ($date === null && ! empty($order->order_details?->delivery_date)) {
            try {
                $date = \Carbon\Carbon::parse((string) $order->order_details->delivery_date)->startOfDay();
            } catch (\Throwable $e) {
                $date = null;
            }
        }

        $this->journal->postBalancedJournal($normalized, $header, (int) $order->id, $batch, null, $date);

        return $this->hasDeliveryBatch($order) ? 'posted' : 'failed';
    }

    /**
     * @param  list<array{account_id: int, debit: float, credit: float, description: string}>  $lines
     * @return list<array{account_id: int, account_code: string|null, account_name: string|null, debit: float, credit: float, description: string}>
     */
    private function decorateLines(array $lines): array
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
     * @return list<array{account_id: int, debit: float, credit: float, description: string}>
     */
    private function buildTransferLines(
        TreeAccount $customerAccount,
        ?ShippingCompany $shippingCo,
        $collectionCo,
        ?int $shippingReceivableAcc,
        ?int $collectionReceivableAcc,
        float $codToTransfer,
        float $prepaidToTransfer
    ): array {
        $lines = [];
        if ($codToTransfer > 0.009) {
            $drAccountId = $shippingReceivableAcc
                ? (int) $shippingReceivableAcc
                : (int) $customerAccount->id;
            if ($drAccountId !== (int) $customerAccount->id) {
                $lines[] = [
                    'account_id' => $drAccountId,
                    'debit' => $codToTransfer,
                    'credit' => 0.0,
                    'description' => 'ذمة شركة الشحن/المندوب — مستحق تحصيل عند التسليم ('.($shippingCo->name ?? '').')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0.0,
                    'credit' => $codToTransfer,
                    'description' => 'تخفيض ذمة العميل — نُقلت إلى شركة الشحن/المندوب',
                ];
            }
        }
        if ($prepaidToTransfer > 0.009) {
            $drAccountId = $collectionReceivableAcc
                ? (int) $collectionReceivableAcc
                : (int) $customerAccount->id;
            if ($drAccountId !== (int) $customerAccount->id) {
                $lines[] = [
                    'account_id' => $drAccountId,
                    'debit' => $prepaidToTransfer,
                    'credit' => 0.0,
                    'description' => 'ذمة شركة التحصيل — جزء مدفوع مقدماً ('.($collectionCo->name ?? '').')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0.0,
                    'credit' => $prepaidToTransfer,
                    'description' => 'تخفيض ذمة العميل — نُقلت إلى شركة التحصيل',
                ];
            }
        }

        return $lines;
    }

    /**
     * عكس قيود التسليم عند رفض الاستلام بعد تأكيد التسليم.
     */
    public function reverseDeliveryTransfer(Order $order): void
    {
        $pattern = 'DELIVERY-' . $order->id . '-%';
        $accountIds = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', $pattern)
            ->pluck('tree_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($accountIds === []) {
            return;
        }

        AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', $pattern)
            ->delete();

        $accounting = app(AccountingService::class);
        foreach ($accountIds as $accountId) {
            try {
                $accounting->updateAccountHierarchyBalances($accountId);
            } catch (\Throwable $e) {
                Log::warning('DeliveryConfirmation: tree balance rebuild failed after delivery reversal', [
                    'order_id' => $order->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * كم من المديونية مُسجَّل بالفعل (مدين) على حساب الوسيط في قيود فاتورة الطلب الأصلية (ORD-*).
     * إذا كان SalesOrderAccountingService وضع الذمة مباشرة على شركة الشحن/التحصيل
     * عند إنشاء أو تحديث الفاتورة، فلا حاجة لنقلها مرة أخرى عند تأكيد التسليم.
     */
    /**
     * لقطة التقسيم المخزّنة (شحن/تحصيل) تُستخدم فقط إن كانت تحمل مبلغاً؛
     * بعد «تم التحصيل» يصفّرها النظام تشغيلياً فنعود للتقسيم المحسوب من الطلب.
     */
    private function hasUsableStoredSplit(OrderDetails $od): bool
    {
        if ($od->shipping_receivable_amount === null || $od->collection_receivable_amount === null) {
            return false;
        }

        return round((float) $od->shipping_receivable_amount + (float) $od->collection_receivable_amount, 2) > 0.009;
    }

    private function receivableAlreadyOnAccount(int $orderId, ?int $accountId): float
    {
        if (!$accountId) {
            return 0;
        }

        return round((float) AccountEntry::where('order_id', $orderId)
            ->where('entry_batch_code', 'like', 'ORD-' . $orderId . '-%')
            ->where('tree_account_id', $accountId)
            ->sum('debit'), 2);
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
}
