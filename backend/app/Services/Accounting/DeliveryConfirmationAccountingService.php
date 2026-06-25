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
    public function recordDeliveryReceivableTransfer(Order $order): array
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

        if ($od->shipping_receivable_amount !== null || $od->collection_receivable_amount !== null) {
            $codPart = round((float) ($od->shipping_receivable_amount ?? 0), 2);
            $prepaidPart = round((float) ($od->collection_receivable_amount ?? 0), 2);
        }

        $alreadyOnShipping = $this->receivableAlreadyOnAccount($order->id, $shippingReceivableAcc);
        $alreadyOnCollection = $this->receivableAlreadyOnAccount($order->id, $collectionReceivableAcc);

        $codToTransfer = round(max(0, $codPart - $alreadyOnShipping), 2);
        $prepaidToTransfer = round(max(0, $prepaidPart - $alreadyOnCollection), 2);

        $batchCode = 'DELIVERY-' . $order->id . '-' . now()->format('YmdHis');
        $desc = 'تأكيد تسليم — نقل ذمة العميل إلى المندوب/شركة الشحن — طلب رقم ' . $order->id;

        $lines = [];

        if ($codToTransfer > 0.009) {
            $drAccountId = $shippingReceivableAcc
                ? (int) $shippingReceivableAcc
                : (int) $customerAccount->id;

            if ($drAccountId !== (int) $customerAccount->id) {
                $lines[] = [
                    'account_id' => $drAccountId,
                    'debit' => $codToTransfer,
                    'credit' => 0,
                    'description' => 'ذمة شركة الشحن/المندوب — مستحق تحصيل عند التسليم (' . ($shippingCo->name ?? '') . ')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0,
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
                    'credit' => 0,
                    'description' => 'ذمة شركة التحصيل — جزء مدفوع مقدماً (' . ($collectionCo->name ?? '') . ')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0,
                    'credit' => $prepaidToTransfer,
                    'description' => 'تخفيض ذمة العميل — نُقلت إلى شركة التحصيل',
                ];
            }
        }

        if (empty($lines)) {
            Log::info('DeliveryConfirmation: no receivable transfer needed (already on intermediary or no intermediary accounts)', [
                'order_id' => $order->id,
                'already_on_shipping' => $alreadyOnShipping,
                'already_on_collection' => $alreadyOnCollection,
            ]);
            return ['batch_code' => $batchCode, 'transferred_amount' => $grandTotal];
        }

        return DB::transaction(function () use ($lines, $desc, $order, $batchCode, $grandTotal) {
            $this->journal->postBalancedJournal($lines, $desc, $order->id, $batchCode);

            return ['batch_code' => $batchCode, 'transferred_amount' => $grandTotal];
        });
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
