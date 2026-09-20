<?php

namespace App\Services\Accounting;

use App\Models\ServiceAccount;
use Illuminate\Support\Facades\DB;

/**
 * مزامنة الرصيد التشغيلي للحساب الخدمي مع القيود المحاسبية.
 */
class ServiceAccountOperationalLedgerService
{
    public function findByAccountId(int $treeAccountId): ?ServiceAccount
    {
        return ServiceAccount::query()->where('account_id', $treeAccountId)->first();
    }

    /**
     * @return array{balance_before: float, balance_after: float}
     */
    public function recordOperationalMovement(
        ServiceAccount $account,
        float $signedAmount,
        string $details,
        string $ref,
        ?string $date = null
    ): array {
        if (abs($signedAmount) < 0.000001) {
            return [
                'balance_before' => (float) $account->balance,
                'balance_after' => (float) $account->balance,
            ];
        }

        return DB::transaction(function () use ($account, $signedAmount, $details, $ref, $date) {
            $locked = ServiceAccount::query()->lockForUpdate()->find($account->id);
            if (! $locked) {
                throw new \RuntimeException('الحساب الخدمي غير موجود');
            }

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore + $signedAmount, 2);
            $locked->balance = $balanceAfter;
            $locked->save();

            return [
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ];
        });
    }

    public function syncFromJournalLine(
        int $treeAccountId,
        float $debit,
        float $credit,
        string $details,
        string $ref,
        ?string $date = null
    ): void {
        $account = $this->findByAccountId($treeAccountId);
        if (! $account) {
            return;
        }

        $signed = round($debit - $credit, 2);
        if (abs($signed) < 0.000001) {
            return;
        }

        $this->recordOperationalMovement($account, $signed, $details, $ref, $date);
    }

    public function syncFromVoucher(
        int $treeAccountId,
        string $voucherType,
        float $amount,
        int $voucherId,
        ?string $notes,
        ?string $date,
        bool $reverse
    ): void {
        $account = $this->findByAccountId($treeAccountId);
        if (! $account) {
            return;
        }

        $signed = $voucherType === 'receipt' ? $amount : -$amount;
        if ($reverse) {
            $signed *= -1;
        }

        if (abs($signed) < 0.000001) {
            return;
        }

        $details = 'سند ' . ($voucherType === 'receipt' ? 'قبض' : 'صرف') . ' رقم ' . $voucherId;
        if ($notes) {
            $details .= ' - ' . $notes;
        }

        $this->recordOperationalMovement(
            $account,
            $signed,
            $details,
            'V' . $voucherId,
            $date ?? date('Y-m-d')
        );
    }
}
