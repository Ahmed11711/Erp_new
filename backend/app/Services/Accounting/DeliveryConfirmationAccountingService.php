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

        $shippingReceivableAcc = $shippingCo?->receivable_tree_account_id;
        $collectionReceivableAcc = app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);

        $prepaidPart = round(min($prepaid, $grandTotal), 2);
        $codPart = round(max(0, $grandTotal - $prepaidPart), 2);

        if ($od->shipping_receivable_amount !== null || $od->collection_receivable_amount !== null) {
            $codPart = round((float) ($od->shipping_receivable_amount ?? 0), 2);
            $prepaidPart = round((float) ($od->collection_receivable_amount ?? 0), 2);
        }

        $batchCode = 'DELIVERY-' . $order->id . '-' . now()->format('YmdHis');
        $desc = 'تأكيد تسليم — نقل ذمة العميل إلى المندوب/شركة الشحن — طلب رقم ' . $order->id;

        $lines = [];

        if ($codPart > 0.009) {
            $drAccountId = $shippingReceivableAcc
                ? (int) $shippingReceivableAcc
                : (int) $customerAccount->id;

            if ($drAccountId !== (int) $customerAccount->id) {
                $lines[] = [
                    'account_id' => $drAccountId,
                    'debit' => $codPart,
                    'credit' => 0,
                    'description' => 'ذمة شركة الشحن/المندوب — مستحق تحصيل عند التسليم (' . ($shippingCo->name ?? '') . ')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0,
                    'credit' => $codPart,
                    'description' => 'تخفيض ذمة العميل — نُقلت إلى شركة الشحن/المندوب',
                ];
            }
        }

        if ($prepaidPart > 0.009) {
            $drAccountId = $collectionReceivableAcc
                ? (int) $collectionReceivableAcc
                : (int) $customerAccount->id;

            if ($drAccountId !== (int) $customerAccount->id) {
                $lines[] = [
                    'account_id' => $drAccountId,
                    'debit' => $prepaidPart,
                    'credit' => 0,
                    'description' => 'ذمة شركة التحصيل — جزء مدفوع مقدماً (' . ($collectionCo->name ?? '') . ')',
                ];
                $lines[] = [
                    'account_id' => (int) $customerAccount->id,
                    'debit' => 0,
                    'credit' => $prepaidPart,
                    'description' => 'تخفيض ذمة العميل — نُقلت إلى شركة التحصيل',
                ];
            }
        }

        if (empty($lines)) {
            Log::info('DeliveryConfirmation: no receivable transfer needed (no intermediary accounts)', [
                'order_id' => $order->id,
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
        AccountEntry::where('order_id', $order->id)
            ->where('entry_batch_code', 'like', $pattern)
            ->delete();
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
