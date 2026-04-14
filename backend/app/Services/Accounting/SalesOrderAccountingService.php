<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Double-entry accounting for sales orders.
 *
 * Invoice (order recognition):
 *   Dr  Customer (AR)     = net product + shipping charged to customer
 *   Cr  Sales revenue = net product (after discount)
 *   Cr  Shipping revenue  = shipping charged to customer (if applicable)
 *
 * Prepaid / advance on the same order (cash in):
 *   Dr  Cash/Bank/Safe
 *   Cr  Customer (AR)
 *
 * Customer AR balance convention (sub-ledger): debit − credit
 *   > 0  => customer still owes (receivable)
 *   < 0  => customer prepaid / credit (unapplied receipts)
 *
 * Courier cost (ShippingCourierAccountingService) never posts to the customer account.
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
     * Rebuild invoice recognition lines after order totals / lines change.
     * Removes only invoice batches (ORD-{id}-*), not prepaid (ORD-PREPAID-*) or collections (PARTCOLLECT-*).
     */
    public function refreshOrderRecognition(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $pattern = 'ORD-' . $order->id . '-%';
            AccountEntry::where('order_id', $order->id)
                ->where('entry_batch_code', 'like', $pattern)
                ->delete();

            $this->writeEntries($order, skipPrepaid: true);
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

        $lines = [
            [
                'account_id' => $customerAccount->id,
                'debit' => $grandTotal,
                'credit' => 0,
                'description' => 'ذمم العميل — إجمالي الفاتورة',
            ],
        ];

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

            $this->journal->postBalancedJournal(
                [
                    [
                        'account_id' => $cashAccountId,
                        'debit' => $prepaid,
                        'credit' => 0,
                        'description' => 'تحصيل دفعة مقدمة — نقدية/بنك',
                    ],
                    [
                        'account_id' => $customerAccount->id,
                        'debit' => 0,
                        'credit' => $prepaid,
                        'description' => 'تخفيض ذمة العميل — دفعة مقدمة',
                    ],
                ],
                $prepaidDesc,
                $order->id,
                $prepaidBatch
            );
        }
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
            $order->company_id
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
