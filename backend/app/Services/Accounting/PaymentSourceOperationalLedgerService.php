<?php

namespace App\Services\Accounting;

/**
 * مزامنة الرصيد التشغيلي لمصدر النقد (بنك / خزينة / حساب خدمي) مع القيود المحاسبية.
 */
class PaymentSourceOperationalLedgerService
{
    public function __construct(
        private readonly BankOperationalLedgerService $bankLedger,
        private readonly SafeOperationalLedgerService $safeLedger,
        private readonly ServiceAccountOperationalLedgerService $serviceAccountLedger,
    ) {}

    public function syncFromVoucher(
        int $treeAccountId,
        string $voucherType,
        float $amount,
        int $voucherId,
        ?string $notes,
        ?string $date,
        bool $reverse
    ): void {
        if ($this->bankLedger->findByAssetId($treeAccountId)) {
            $this->bankLedger->syncFromVoucher(
                $treeAccountId,
                $voucherType,
                $amount,
                $voucherId,
                $notes,
                $date,
                $reverse
            );

            return;
        }

        if ($this->safeLedger->findByAccountId($treeAccountId)) {
            $this->safeLedger->syncFromVoucher(
                $treeAccountId,
                $voucherType,
                $amount,
                $voucherId,
                $notes,
                $date,
                $reverse
            );

            return;
        }

        $this->serviceAccountLedger->syncFromVoucher(
            $treeAccountId,
            $voucherType,
            $amount,
            $voucherId,
            $notes,
            $date,
            $reverse
        );
    }

    /**
     * مزامنة رصيد مصدر النقد بعد ترحيل سطر في القيد اليومي (خزينة/بنك/حساب خدمي فقط).
     */
    public function syncFromJournalLine(
        int $treeAccountId,
        float $debit,
        float $credit,
        string $details,
        string $ref,
        ?string $date = null
    ): void {
        if ($this->bankLedger->findByAssetId($treeAccountId)) {
            $signed = round($debit - $credit, 2);
            if (abs($signed) < 0.000001) {
                return;
            }

            $bank = $this->bankLedger->findByAssetId($treeAccountId);
            if ($bank) {
                if ($this->bankLedger->hasOperationalDetailForRef($bank, $ref)) {
                    return;
                }

                $this->bankLedger->recordOperationalMovement(
                    $bank,
                    $signed,
                    $details,
                    $ref,
                    'قيود',
                    null,
                    $date ?? date('Y-m-d')
                );
            }

            return;
        }

        if ($this->safeLedger->findByAccountId($treeAccountId)) {
            $this->safeLedger->syncFromJournalLine(
                $treeAccountId,
                $debit,
                $credit,
                $details,
                $ref,
                $date
            );

            return;
        }

        $this->serviceAccountLedger->syncFromJournalLine(
            $treeAccountId,
            $debit,
            $credit,
            $details,
            $ref,
            $date
        );
    }
}
