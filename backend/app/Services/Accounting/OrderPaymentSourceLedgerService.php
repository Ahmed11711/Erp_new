<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\Order;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\Log;

/**
 * يربط حركات مصادر النقد التشغيلية (بنك / خزينة / حساب خدمي) بالقيود في شجرة الحسابات.
 *
 * كل حركة: تحديث الرصيد التشغيلي + قيد محاسبي (مدين مصدر النقد / دائن ذمة — أو العكس عند الإرجاع).
 */
class OrderPaymentSourceLedgerService
{
    public const BATCH_PREFIX = 'ORD-OPS-';

    public function __construct(
        private BankOperationalLedgerService $bankLedger,
        private SafeOperationalLedgerService $safeLedger,
        private LedgerJournalService $journal,
        private SalesOrderAccountingService $salesOrderAccounting,
        private AccountLinkingService $accountLinking,
    ) {}

    /**
     * @param  'bank'|'safe'|'service_account'  $paymentType
     */
    public function recordFromPaymentType(
        string $paymentType,
        int $sourceId,
        float $signedAmount,
        Order $order,
        string $details,
        string $ref,
        string $movementType,
        int $userId,
        ?string $date = null,
        ?int $creditReceivableAccountId = null,
    ): void {
        if (abs($signedAmount) < 0.000001) {
            return;
        }

        match ($paymentType) {
            'safe' => $this->recordSafeMovement(
                Safe::findOrFail($sourceId),
                $signedAmount,
                $order,
                $details,
                $ref,
                $movementType,
                $userId,
                $date,
                $creditReceivableAccountId,
            ),
            'service_account' => $this->recordServiceAccountMovement(
                ServiceAccount::findOrFail($sourceId),
                $signedAmount,
                $order,
                $details,
                $ref,
                $movementType,
                $userId,
                $date,
                $creditReceivableAccountId,
            ),
            default => $this->recordBankMovement(
                Bank::findOrFail($sourceId),
                $signedAmount,
                $order,
                $details,
                $ref,
                $movementType,
                $userId,
                $date,
                $creditReceivableAccountId,
            ),
        };
    }

    public function recordBankMovement(
        Bank $bank,
        float $signedAmount,
        Order $order,
        string $details,
        string $ref,
        string $movementType,
        int $userId,
        ?string $date = null,
        ?int $creditReceivableAccountId = null,
    ): void {
        $this->recordMovement(
            (int) ($bank->asset_id ?? 0),
            fn () => $this->bankLedger->recordOperationalMovement(
                $bank,
                $signedAmount,
                $details,
                $ref,
                $movementType,
                $userId,
                $date,
            ),
            $signedAmount,
            $order,
            $details,
            $ref,
            $date,
            $creditReceivableAccountId,
            'البنك غير مرتبط بحساب في شجرة الحسابات — تم تحديث الرصيد التشغيلي فقط',
            ['bank_id' => $bank->id, 'order_id' => $order->id],
        );
    }

    public function recordSafeMovement(
        Safe $safe,
        float $signedAmount,
        Order $order,
        string $details,
        string $ref,
        string $movementType,
        int $userId,
        ?string $date = null,
        ?int $creditReceivableAccountId = null,
    ): void {
        $this->recordMovement(
            (int) ($safe->account_id ?? 0),
            fn () => $this->safeLedger->recordOperationalMovement(
                $safe,
                $signedAmount,
                $details,
                $ref,
                $movementType,
                $userId,
                $date,
            ),
            $signedAmount,
            $order,
            $details,
            $ref,
            $date,
            $creditReceivableAccountId,
            'الخزينة غير مرتبطة بحساب في شجرة الحسابات — تم تحديث الرصيد التشغيلي فقط',
            ['safe_id' => $safe->id, 'order_id' => $order->id],
        );
    }

    public function recordServiceAccountMovement(
        ServiceAccount $account,
        float $signedAmount,
        Order $order,
        string $details,
        string $ref,
        string $movementType,
        int $userId,
        ?string $date = null,
        ?int $creditReceivableAccountId = null,
    ): void {
        $this->recordMovement(
            (int) ($account->account_id ?? 0),
            function () use ($account, $signedAmount) {
                $account->update(['balance' => round((float) $account->balance + $signedAmount, 2)]);
            },
            $signedAmount,
            $order,
            $details,
            $ref,
            $date,
            $creditReceivableAccountId,
            'الحساب الخدمي غير مرتبط بحساب في شجرة الحسابات — تم تحديث الرصيد التشغيلي فقط',
            ['service_account_id' => $account->id, 'order_id' => $order->id],
        );
    }

    /**
     * @param  callable(): mixed  $recordOperational
     * @param  array<string, mixed>  $logContext
     */
    private function recordMovement(
        int $cashTreeAccountId,
        callable $recordOperational,
        float $signedAmount,
        Order $order,
        string $details,
        string $ref,
        ?string $date,
        ?int $creditReceivableAccountId,
        string $missingLinkMessage,
        array $logContext,
    ): void {
        $recordOperational();

        if ($cashTreeAccountId <= 0) {
            Log::warning('OrderPaymentSourceLedgerService: '.$missingLinkMessage, $logContext);

            return;
        }

        $creditAccountId = $creditReceivableAccountId ?? $this->resolveCreditReceivableAccountId($order);
        if (! $creditAccountId) {
            Log::warning('OrderPaymentSourceLedgerService: cannot resolve receivable account for GL', $logContext);

            return;
        }

        if ($cashTreeAccountId === $creditAccountId) {
            Log::warning('OrderPaymentSourceLedgerService: cash and receivable accounts are the same — GL skipped', $logContext);

            return;
        }

        TreeAccount::findOrFail($cashTreeAccountId);
        TreeAccount::findOrFail($creditAccountId);

        $batchCode = self::BATCH_PREFIX.$order->id.'-'.$ref.'-'.now()->format('YmdHis');
        $journalDesc = trim($details).' — طلب رقم '.$order->id;
        $amount = round(abs($signedAmount), 2);

        try {
            if ($signedAmount >= 0) {
                $this->journal->postBalancedJournal(
                    [
                        [
                            'account_id' => $cashTreeAccountId,
                            'debit' => $amount,
                            'credit' => 0,
                            'description' => $journalDesc.' — تحصيل/إيداع',
                        ],
                        [
                            'account_id' => $creditAccountId,
                            'debit' => 0,
                            'credit' => $amount,
                            'description' => $journalDesc.' — تخفيض ذمة',
                        ],
                    ],
                    $journalDesc,
                    $order->id,
                    $batchCode,
                    null,
                    $date,
                    false,
                );
            } else {
                $this->journal->postBalancedJournal(
                    [
                        [
                            'account_id' => $creditAccountId,
                            'debit' => $amount,
                            'credit' => 0,
                            'description' => $journalDesc.' — إعادة ذمة',
                        ],
                        [
                            'account_id' => $cashTreeAccountId,
                            'debit' => 0,
                            'credit' => $amount,
                            'description' => $journalDesc.' — إرجاع نقدية',
                        ],
                    ],
                    $journalDesc,
                    $order->id,
                    $batchCode,
                    null,
                    $date,
                    false,
                );
            }
        } catch (\Throwable $e) {
            Log::error('OrderPaymentSourceLedgerService: GL posting failed', array_merge($logContext, [
                'error' => $e->getMessage(),
            ]));
        }
    }

    private function resolveCreditReceivableAccountId(Order $order): ?int
    {
        $fromPrepaid = $this->salesOrderAccounting->resolvePrepaidCreditTreeAccountId($order);
        if ($fromPrepaid) {
            return $fromPrepaid;
        }

        $customer = $this->accountLinking->resolveOrderCustomerAccount(
            $order->customer_type ?? 'فرد',
            $order->customer_name,
            $order->customer_phone_1,
            $order->company_id,
            $order->order_source_id ? (int) $order->order_source_id : null,
        );

        return $customer?->id;
    }
}
