<?php

namespace App\Observers;

use App\Models\AccountEntry;
use App\Models\Order;
use App\Models\TreeAccount;
use App\Models\Transaction;
use App\Services\Accounting\LedgerJournalService;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\Accounting\AccountingService;
use App\Services\Orders\OrderStatusHistoryService;
use App\Services\Shipping\OrderFinancialStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class OrderObserver
{
    public function __construct(
        private SalesOrderAccountingService $salesOrderAccounting
    ) {
    }

    public function created(Order $order): void
    {
        try {
            $this->salesOrderAccounting->recordInitialOrderRecognition($order);
            $this->storeInTransaction($order, 'create', (float) $order->total_invoice);
        } catch (\Throwable $e) {
            Log::error('OrderObserver: Failed to create account entries', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Order $order): void
    {
        try {
            if ($order->wasChanged('order_status') && ! OrderStatusHistoryService::$suppressAutoRecord) {
                app(OrderStatusHistoryService::class)->record(
                    (int) $order->id,
                    $order->getOriginal('order_status'),
                    (string) $order->order_status,
                    OrderStatusHistoryService::$pendingReason,
                    auth()->id(),
                );
                OrderStatusHistoryService::$pendingReason = null;
            }

            if ($order->wasChanged('prepaid_amount')) {
                $old = (float) ($order->getOriginal('prepaid_amount') ?? 0);
                $new = (float) $order->prepaid_amount;
                $diff = round($new - $old, 2);
                if (! \App\Services\Orders\OrderEditAccountingService::$suppressPrepaidDeltaJournal) {
                    if ($diff > 0.009) {
                        $this->recordAdditionalPrepaidCollection($order, $diff);
                    } elseif ($diff < -0.009) {
                        $this->recordPrepaidReductionReversal($order, abs($diff));
                    }
                }

                Log::info('OrderObserver: prepaid change', [
                    'order_id' => $order->id,
                    'old' => $old,
                    'new' => $new,
                    'diff' => $diff,
                ]);
            }

            if ($order->wasChanged(['net_total', 'prepaid_amount'])) {
                DB::afterCommit(function () use ($order) {
                    $fresh = Order::with('order_details')->find($order->id);
                    if (! $fresh?->order_details) {
                        return;
                    }
                    try {
                        app(OrderFinancialStateService::class)->syncFromOrder($fresh);
                    } catch (\Throwable $e) {
                        Log::error('OrderObserver: syncFromOrder failed', [
                            'order_id' => $fresh->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }

            $financialRecognitionFields = [
                'total_invoice',
                'discount',
                'shipping_revenue',
                'shipping_cost',
                'net_total',
                'prepaid_amount',
                'customer_name',
                'customer_phone_1',
                'customer_type',
                'company_id',
                'bank_id',
                'prepaid_payment_type',
            ];
            if ($order->wasChanged($financialRecognitionFields)) {
                // إعادة بناء ORD-PREPAID-* عند تغيير مصدر الدفع فقط — لا عند تغيير المبلغ وحده
                // لأن الزيادة/النقصان تُسجَّل عبر PARTCOLLECT-* / PREPAID-REV-*.
                $rebuildPrepaid = $order->wasChanged(['bank_id', 'prepaid_payment_type'])
                    && (float) ($order->prepaid_amount ?? 0) > 0.009;

                DB::afterCommit(function () use ($order, $rebuildPrepaid) {
                    $fresh = Order::with('order_products')->find($order->id);
                    if (!$fresh) {
                        return;
                    }
                    try {
                        $this->salesOrderAccounting->refreshOrderRecognition($fresh, $rebuildPrepaid);
                    } catch (\Throwable $e) {
                        Log::error('OrderObserver: refreshOrderRecognition failed', [
                            'order_id' => $fresh->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::error('OrderObserver accounting error', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Additional cash-in after order exists: Dr Cash/Bank/Safe, Cr Customer (same as collection on account).
     */
    private function recordAdditionalPrepaidCollection(Order $order, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $amount) {
            $customerAccount = $this->resolveCustomerAccount($order);
            if (!$customerAccount) {
                Log::error('OrderObserver: cannot resolve customer account for prepaid increase', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            $cashAccountId = $this->salesOrderAccounting->resolveCashTreeAccountIdForOrder($order);
            if (!$cashAccountId) {
                Log::error('OrderObserver: cannot resolve cash account for prepaid increase', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            $batchCode = 'PARTCOLLECT-' . $order->id . '-' . now()->format('YmdHis');
            $desc = 'تحصيل جزئي / زيادة دفعة مقدمة — طلب رقم ' . $order->id;

            $creditReceivableId = $this->salesOrderAccounting->resolvePrepaidCreditTreeAccountId($order)
                ?? $customerAccount->id;

            app(LedgerJournalService::class)->postCustomerCollection(
                $creditReceivableId,
                $cashAccountId,
                $amount,
                $desc,
                $order->id,
                $batchCode
            );

            $this->storeInTransaction($order, 'update');
        });
    }

    /**
     * Prepaid reduced on edit: Dr Customer, Cr Cash (refund / reclass).
     */
    private function recordPrepaidReductionReversal(Order $order, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $amount) {
            $customerAccount = $this->resolveCustomerAccount($order);
            if (!$customerAccount) {
                Log::error('OrderObserver: cannot resolve customer account for prepaid reversal', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            $cashAccountId = $this->salesOrderAccounting->resolveCashTreeAccountIdForOrder($order);
            if (!$cashAccountId) {
                Log::error('OrderObserver: cannot resolve cash account for prepaid reversal', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            $batchCode = 'PREPAID-REV-' . $order->id . '-' . now()->format('YmdHis');
            $desc = 'تخفيض دفعة مقدمة — طلب رقم ' . $order->id;

            $creditReceivableId = $this->salesOrderAccounting->resolvePrepaidCreditTreeAccountId($order)
                ?? $customerAccount->id;

            app(LedgerJournalService::class)->postCustomerCollectionReversal(
                $creditReceivableId,
                $cashAccountId,
                $amount,
                $desc,
                $order->id,
                $batchCode
            );
        });
    }

    private function resolveCustomerAccount(Order $order): ?TreeAccount
    {
        $accountLinkingService = app(\App\Services\Accounting\AccountLinkingService::class);

        return $accountLinkingService->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null
        );
    }

    public function storeInTransaction($order, $type, $netAmount = 0): void
    {
        if ($type == 'create') {
            $prepaid_amount = 0;
            $net_total = $netAmount;
        } else {
            $prepaid_amount = $order->prepaid_amount - ($order->getOriginal('prepaid_amount') ?: 0);
            $net_total = 0;
        }

        Transaction::create([
            'order_id' => $order->id,
            'phone' => $order->customer_phone_1 ?? '010161582010',
            'net_total' => $net_total,
            'prepaid_amount' => $prepaid_amount,
        ]);
    }

    /**
     * @deprecated Legacy helper — prefer {@see LedgerJournalService}. Kept for external callers if any.
     */
    public function createAccountTreeBank($bankName, $expenseType, $amount)
    {
        $treeBank = TreeAccount::where('name', $bankName)->where('level', 4)->first();
        $treeExpense = TreeAccount::where('name', $expenseType)->where('level', 4)->first();

        $finalEntries = [];
        $finalEntries[] = [
            'account_id' => $treeExpense->id ?? null,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'Expense Recorded',
        ];
        $finalEntries[] = [
            'account_id' => $treeBank->id ?? null,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'Bank Withdrawal - Expense Payment',
        ];

        $batchCode = 'EXP-' . now()->format('YmdHis');
        $accService = app(AccountingService::class);

        foreach ($finalEntries as $entry) {
            if (!$entry['account_id']) {
                continue;
            }

            AccountEntry::create([
                'tree_account_id' => $entry['account_id'],
                'debit' => $entry['debit'],
                'credit' => $entry['credit'],
                'description' => $entry['description'],
                'order_id' => null,
                'entry_batch_code' => $batchCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $accService->updateAccountHierarchyBalances($entry['account_id']);
            } catch (\Exception $e) {
                Log::warning('OrderObserver::createAccountTreeBank hierarchy update failed', [
                    'account_id' => $entry['account_id'],
                ]);
            }
        }
    }
}
