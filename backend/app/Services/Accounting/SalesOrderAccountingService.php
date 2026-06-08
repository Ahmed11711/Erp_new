<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Shipping\CollectionReceivableAccountResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Double-entry accounting for sales orders.
 *
 * Invoice (order recognition):
 *   Dr  Receivables (Customer و/أو ذمم وسيط شحن/تحصيل حسب الإعداد)
 *   Cr  Sales revenue + Shipping revenue
 *
 * تقسيم الذمم (عند ربط حسابات أصول لشركة الشحن و/أو شركة التحصيل في order_details):
 *   - جزء الدفعة المقدمة prepayment → حساب ذمة شركة التحصيل (مثل Paymob) إن وُجد، وإلا ذمة العميل
 *   - الباقي (تحصيل عند التسليم) → ذمة شركة الشحن (مثل Bosta) إن وُجد، وإلا ذمة العميل
 *
 * Prepaid / advance on the same order (cash in):
 *   Dr  Cash/Bank/Safe
 *   Cr  نفس حساب الذمة المستخدم في الفاتورة للجزء المدفوع مقدماً (شركة تحصيل أو عميل)
 *
 * Courier cost (ShippingCourierAccountingService) لا يمر على ذمم المبيعات.
 */
class SalesOrderAccountingService
{
    public function __construct(
        private LedgerJournalService $journal
    ) {
    }

    public function recordInitialOrderRecognition(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->writeEntries($order);
        });
    }

    /**
     * إعادة بناء قيود الفاتورة؛ واختيارياً إعادة بناء قيود الدفعة المقدمة بعد ربط شركات الشحن/التحصيل.
     *
     * @param  bool  $rebuildPrepaid  عند true يُحذف ORD-PREPAID-* ويُعاد إنشاؤه بمحاسبة وسيط التحصيل
     */
    public function refreshOrderRecognition(Order $order, bool $rebuildPrepaid = false): void
    {
        DB::transaction(function () use ($order, $rebuildPrepaid) {
            $pattern = 'ORD-' . $order->id . '-%';
            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', $pattern)
                ->delete();

            if ($rebuildPrepaid) {
                AccountEntry::where('order_id', $order->id)
                    ->where('entry_batch_code', 'like', 'ORD-PREPAID-' . $order->id . '-%')
                    ->delete();
            }

            $this->writeEntries($order, skipPrepaid: ! $rebuildPrepaid);
        });
    }

    private function writeEntries(Order $order, bool $skipPrepaid = false): void
    {
        $customerAccount = $this->resolveCustomerAccount($order);
        if (!$customerAccount) {
            Log::warning('SalesOrderAccountingService: cannot resolve customer account', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $salesAcc = TreeAccount::resolveSalesRevenueAccount();
        $shippingRevenueAcc = TreeAccount::resolveShippingRevenueAccount();

        if (!$salesAcc) {
            Log::warning('SalesOrderAccountingService: missing sales revenue account', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $productTotal = $this->resolveProductTotal($order);
        $shippingRevenue = (float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0);
        $discount = (float) ($order->discount ?? 0);

        $netProductSales = max(0, $productTotal - $discount);
        $grandTotal = $netProductSales + $shippingRevenue;

        if ($grandTotal <= 0) {
            return;
        }

        $batchCode = 'ORD-' . $order->id . '-' . now()->format('YmdHis');
        $desc = 'فاتورة مبيعات — طلب رقم ' . $order->id;

        $receivableDebits = $this->buildSplitReceivableDebits($order, $customerAccount, $grandTotal);
        $lines = [];
        foreach ($receivableDebits as $row) {
            $lines[] = [
                'account_id' => $row['account_id'],
                'debit' => $row['amount'],
                'credit' => 0,
                'description' => $row['description'],
            ];
        }

        if ($netProductSales > 0) {
            $lines[] = [
                'account_id' => $salesAcc->id,
                'debit' => 0,
                'credit' => $netProductSales,
                'description' => 'إيرادات المبيعات (بضاعة)',
            ];
        }

        if ($shippingRevenue > 0 && $shippingRevenueAcc) {
            $lines[] = [
                'account_id' => $shippingRevenueAcc->id,
                'debit' => 0,
                'credit' => $shippingRevenue,
                'description' => 'إيراد شحن وتوصيل محصل من العميل',
            ];
        } elseif ($shippingRevenue > 0 && !$shippingRevenueAcc) {
            $lines[] = [
                'account_id' => $salesAcc->id,
                'debit' => 0,
                'credit' => $shippingRevenue,
                'description' => 'إيراد شحن (لم يُعثر على حساب إيراد شحن منفصل)',
            ];
        }

        $this->journal->postBalancedJournal($lines, $desc, $order->id, $batchCode);

        if ($skipPrepaid) {
            return;
        }

        if ($order->prepaid_amount > 0) {
            $prepaid = (float) $order->prepaid_amount;
            $cashAccountId = $this->resolveCashAccountIdForPrepaid($order);
            if (!$cashAccountId) {
                Log::warning('SalesOrderAccountingService: prepaid present but cash account unresolved', [
                    'order_id' => $order->id,
                    'bank_id' => $order->bank_id,
                ]);

                return;
            }

            $prepaidBatch = 'ORD-PREPAID-' . $order->id . '-' . now()->format('YmdHis');
            $prepaidDesc = 'دفعة مقدمة — طلب رقم ' . $order->id;

            $creditReceivableId = $this->resolvePrepaidCreditTreeAccountId($order) ?? $customerAccount->id;

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
                $prepaidBatch
            );
        }
    }

    /**
     * مدين الفاتورة: توزيع على ذمة شركة تحصيل (جزء مدفوع مقدماً) وذمة شركة شحن (الباقي عند الاستلام).
     *
     * @return array<int, array{account_id: int, amount: float, description: string}>
     */
    private function buildSplitReceivableDebits(Order $order, TreeAccount $customerAccount, float $grandTotal): array
    {
        $order->loadMissing(['order_details.shipping_company', 'order_details.collection_company']);

        $prepaid = (float) ($order->prepaid_amount ?? 0);
        $prepaidPart = round(min($prepaid, $grandTotal), 2);
        $codPart = round(max(0, $grandTotal - $prepaidPart), 2);

        $od = $order->order_details;
        $shipAr = $od?->shipping_company?->receivable_tree_account_id;
        $collectAr = app(CollectionReceivableAccountResolver::class)
            ->receivableAccountIdForOrderDetails($od);

        $buckets = [];

        if ($prepaidPart > 0.009) {
            $aid = $collectAr ? (int) $collectAr : (int) $customerAccount->id;
            $buckets[$aid] = ($buckets[$aid] ?? 0) + $prepaidPart;
        }
        if ($codPart > 0.009) {
            $aid = $shipAr ? (int) $shipAr : (int) $customerAccount->id;
            $buckets[$aid] = ($buckets[$aid] ?? 0) + $codPart;
        }

        $out = [];
        foreach ($buckets as $accountId => $amount) {
            $label = 'ذمم العميل — إجمالي الفاتورة';
            if ($accountId === (int) $customerAccount->id) {
                $label = 'ذمم العميل — إجمالي الفاتورة';
            } elseif ($collectAr && $accountId === (int) $collectAr) {
                $label = 'ذمة شركة تحصيل — جزء مدفوع مقدماً/إلكترونياً';
            } elseif ($shipAr && $accountId === (int) $shipAr) {
                $label = 'ذمة شركة شحن — مستحق عند التحصيل من العميل';
            }

            $out[] = [
                'account_id' => $accountId,
                'amount' => round($amount, 2),
                'description' => $label,
            ];
        }

        if ($out === []) {
            $out[] = [
                'account_id' => (int) $customerAccount->id,
                'amount' => round($grandTotal, 2),
                'description' => 'ذمم العميل — إجمالي الفاتورة',
            ];
        }

        return $out;
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
     * Product subtotal before discount: from order lines when present; otherwise total_invoice − shipping.
     * Order edit refresh runs after DB commit so lines match header totals.
     */
    private function resolveProductTotal(Order $order): float
    {
        $lineTotal = (float) $order->order_products()
            ->sum(DB::raw('COALESCE(total_price, quantity * price)'));

        if ($lineTotal > 0) {
            return round($lineTotal, 2);
        }

        $shipping = (float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0);
        $fromInvoice = (float) $order->total_invoice - $shipping;

        return round(max(0, $fromInvoice), 2);
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
        $paymentType = request()->input('payment_type', 'bank');

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
