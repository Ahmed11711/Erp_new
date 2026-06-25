<?php

namespace App\Services\Orders;

use App\Enums\CollectionProviderType;
use App\Enums\OrderCollectionStatus;
use App\Enums\OrderSettlementStatus;
use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\PendingBankBalance;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\shippingCompanyDetails;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\DeliveryConfirmationAccountingService;
use App\Services\Accounting\SafeOperationalLedgerService;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * عكس المحاسبة التلقائي عند إلغاء الطلب:
 * - قيود الفاتورة والدفعة المقدمة (ORD-* / ORD-PREPAID-* …)
 * - ذمم شركة التحصيل / شركة الشحن / المندوب
 * - أرصدة البنوك/الخزائن التشغيلية (عند وجود نقدية فعلية)
 * - سطور شركة الشحن التشغيلية المفتوحة إن وُجدت
 */
final class OrderCancellationAccountingService
{
    private const MOVEMENT_TYPE = 'الطلبات';

    /** @var list<string> */
    private const OPEN_SHIPPING_DETAIL_STATUSES = ['تم شحن', 'تم التسليم'];

    public function __construct(
        private AccountingService $accountingService,
        private CollectionReceivableAccountResolver $collectionReceivableResolver,
    ) {
    }

    /**
     * هل يحتاج المستخدم لاختيار خزينة/بنك لإرجاع الدفعة المقدمة؟
     * طلبات Shopify/Paymob (مديونية على شركة تحصيل) لا تحتاج — تُعكس الذمة تلقائياً.
     */
    public function requiresManualPrepaidRefundSelection(Order $order): bool
    {
        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        if ($prepaid <= 0.009) {
            return false;
        }

        if ($this->isPrepaidHeldByCollectionIntermediary($order)) {
            return false;
        }

        $paymentType = trim((string) ($order->prepaid_payment_type ?? ''));
        if (in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            return false;
        }
        if ($order->bank_id && $paymentType !== 'pending') {
            return false;
        }

        return in_array($paymentType, ['', 'pending', 'none'], true);
    }

    public function isPrepaidHeldByCollectionIntermediary(Order $order): bool
    {
        $prepaid = (float) ($order->prepaid_amount ?? 0);
        if ($prepaid <= 0.009) {
            return false;
        }

        $paymentType = trim((string) ($order->prepaid_payment_type ?? ''));
        if (in_array($paymentType, ['bank', 'safe', 'service_account'], true)) {
            return false;
        }

        return $this->collectionReceivableResolver->receivableAccountIdForOrder($order) !== null;
    }

    /**
     * @param  array<string, mixed>|null  $refundOptions  moneyReturnedStatus, moneyReturnedBank, …
     */
    public function handleCancellation(Order $order, int $userId, ?array $refundOptions = null): void
    {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);
        $orderDetails = $order->order_details;

        DB::transaction(function () use ($order, $orderDetails, $userId, $refundOptions) {
            if ($order->order_status === 'تم التسليم' && $orderDetails) {
                app(DeliveryConfirmationAccountingService::class)->reverseDeliveryTransfer($order);
            }

            $this->reverseOpenShippingCompanyDetails($order, $orderDetails, $userId);
            $this->reverseOperationalCashMovements($order, $userId, $refundOptions);
            $this->removeAllOrderJournalEntries($order);
            $this->resetReceivableSnapshot($orderDetails);
        });
    }

    /**
     * @param  array<string, mixed>|null  $refundOptions
     */
    private function reverseOperationalCashMovements(Order $order, int $userId, ?array $refundOptions): void
    {
        if ($this->isPrepaidHeldByCollectionIntermediary($order)) {
            return;
        }

        $prepaid = round((float) ($order->prepaid_amount ?? 0), 2);
        if ($prepaid <= 0.009) {
            return;
        }

        $reason = 'إلغاء طلب — إرجاع مبلغ تحت الحساب — طلب رقم '.$order->id;
        $ref = (string) $order->id;

        $status = is_array($refundOptions) ? ($refundOptions['moneyReturnedStatus'] ?? null) : null;

        if ($status === 'pending') {
            PendingBankBalance::create([
                'amount' => -$prepaid,
                'details' => $reason,
                'ref' => $ref,
                'type' => self::MOVEMENT_TYPE,
                'bank_id' => $order->bank_id,
                'user_id' => $userId,
            ]);

            return;
        }

        $manualBankId = is_array($refundOptions) ? ($refundOptions['moneyReturnedBank'] ?? null) : null;
        if ($manualBankId && $status === 'approved') {
            $this->reverseBankNet($order, (int) $manualBankId, -$prepaid, $userId, $reason, $ref);

            return;
        }

        $resolved = $this->resolveStoredPrepaidSource($order);
        if ($resolved !== null) {
            $this->applySourceRefund($order, $resolved, -$prepaid, $userId, $reason, $ref);

            return;
        }

        $this->reverseNetOperationalByLedger($order, $userId, $reason, $ref);
    }

    private function reverseOpenShippingCompanyDetails(Order $order, ?OrderDetails $orderDetails, int $userId): void
    {
        $rows = shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->where('is_done', 0)
            ->whereIn('status', self::OPEN_SHIPPING_DETAIL_STATUSES)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $shippingDate = $orderDetails?->shipping_date ?? now()->toDateString();
        $actor = auth()->user()?->name ?? 'النظام';

        foreach ($rows as $row) {
            $row->is_done = 1;
            $row->save();

            DB::statement('CALL shipping_company_procedure(?, ?, ?, ?, ?, ?, ?)', [
                (int) $row->shipping_company_id,
                $order->id,
                $shippingDate,
                'إلغاء طلب',
                (float) -$row->amount,
                $actor,
                now(),
            ]);
        }
    }

    private function removeAllOrderJournalEntries(Order $order): void
    {
        $accountIds = AccountEntry::query()
            ->where('order_id', $order->id)
            ->pluck('tree_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($accountIds === []) {
            return;
        }

        AccountEntry::query()->where('order_id', $order->id)->delete();

        foreach ($accountIds as $accountId) {
            try {
                $this->accountingService->updateAccountHierarchyBalances($accountId);
            } catch (\Throwable $e) {
                Log::warning('OrderCancellationAccountingService: tree balance rebuild failed', [
                    'order_id' => $order->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function resetReceivableSnapshot(?OrderDetails $orderDetails): void
    {
        if (! $orderDetails) {
            return;
        }

        $orderDetails->shipping_receivable_amount = null;
        $orderDetails->collection_receivable_amount = null;
        $orderDetails->collection_provider_type = CollectionProviderType::None->value;
        $orderDetails->collection_provider_id = null;
        $orderDetails->collection_company_id = null;
        $orderDetails->collection_status = OrderCollectionStatus::NotRequired->value;
        $orderDetails->settlement_status = OrderSettlementStatus::NotApplicable->value;
        $orderDetails->delivery_batch_code = null;
        $orderDetails->liability_transferred_at = null;
        $orderDetails->liability_holder_type = null;
        $orderDetails->liability_holder_id = null;
        $orderDetails->amount_to_collect = 0;
        $orderDetails->remaining_amount = 0;
        $orderDetails->save();
    }

    /**
     * @return array{type: string, id: int}|null
     */
    private function resolveStoredPrepaidSource(Order $order): ?array
    {
        $type = trim((string) ($order->prepaid_payment_type ?? ''));

        if ($type === 'bank' && $order->bank_id) {
            return ['type' => 'bank', 'id' => (int) $order->bank_id];
        }

        if ($type === 'safe' && $order->bank_id) {
            return ['type' => 'safe', 'id' => (int) $order->bank_id];
        }

        if ($type === 'service_account' && $order->bank_id) {
            return ['type' => 'service_account', 'id' => (int) $order->bank_id];
        }

        if ($order->bank_id) {
            return ['type' => 'bank', 'id' => (int) $order->bank_id];
        }

        return null;
    }

    /**
     * @param  array{type: string, id: int}  $source
     */
    private function applySourceRefund(
        Order $order,
        array $source,
        float $signedAmount,
        int $userId,
        string $reason,
        string $ref,
    ): void {
        match ($source['type']) {
            'safe' => $this->reverseSafeNet(
                Safe::find($source['id']),
                $signedAmount,
                $userId,
                $reason,
                $ref
            ),
            'service_account' => $this->reverseServiceAccountNet(
                ServiceAccount::find($source['id']),
                $signedAmount,
                $reason,
                $ref
            ),
            default => $this->reverseBankNet($order, $source['id'], $signedAmount, $userId, $reason, $ref),
        };
    }

    private function reverseNetOperationalByLedger(Order $order, int $userId, string $reason, string $ref): void
    {
        $bankNets = DB::table('bank_details')
            ->where('ref', $ref)
            ->where('type', self::MOVEMENT_TYPE)
            ->selectRaw('bank_id, SUM(balance_after - balance_before) as net')
            ->groupBy('bank_id')
            ->get();

        foreach ($bankNets as $row) {
            $net = round((float) $row->net, 2);
            if (abs($net) < 0.009) {
                continue;
            }
            $this->reverseBankNet($order, (int) $row->bank_id, -$net, $userId, $reason, $ref);
        }
    }

    private function reverseBankNet(
        Order $order,
        int $bankId,
        float $signedAmount,
        int $userId,
        string $reason,
        string $ref,
    ): void {
        if (abs($signedAmount) < 0.009) {
            return;
        }

        $bank = Bank::find($bankId);
        if (! $bank) {
            return;
        }

        app(BankOperationalLedgerService::class)->recordOperationalMovement(
            $bank,
            $signedAmount,
            $reason,
            $ref,
            self::MOVEMENT_TYPE,
            $userId,
            now()->toDateString(),
        );
    }

    private function reverseSafeNet(?Safe $safe, float $signedAmount, int $userId, string $reason, string $ref): void
    {
        if (! $safe || abs($signedAmount) < 0.009) {
            return;
        }

        app(SafeOperationalLedgerService::class)->recordOperationalMovement(
            $safe,
            $signedAmount,
            $reason,
            $ref,
            self::MOVEMENT_TYPE,
            $userId,
            now()->toDateString(),
        );
    }

    private function reverseServiceAccountNet(?ServiceAccount $account, float $signedAmount, string $reason, string $ref): void
    {
        if (! $account || abs($signedAmount) < 0.009) {
            return;
        }

        $account->update([
            'balance' => round((float) $account->balance + $signedAmount, 2),
        ]);
    }

    /**
     * تحويل طلب HTTP لخيارات الإرجاع (للتوافق مع الواجهة الحالية).
     *
     * @return array<string, mixed>
     */
    public function refundOptionsFromRequest(Request $request): array
    {
        $options = [];
        if ($request->has('moneyReturnedStatus')) {
            $options['moneyReturnedStatus'] = $request->input('moneyReturnedStatus');
        }
        if ($request->filled('moneyReturnedBank')) {
            $options['moneyReturnedBank'] = $request->input('moneyReturnedBank');
        }

        return $options;
    }
}
