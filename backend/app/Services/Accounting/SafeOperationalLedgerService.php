<?php

namespace App\Services\Accounting;

use App\Models\Safe;
use App\Models\SafeTransaction;
use Illuminate\Support\Facades\DB;

/**
 * يربط رصيد الخزينة التشغيلي (safes.balance) بحركات حساب الشجرة المرتبط.
 */
class SafeOperationalLedgerService
{
    public function findByAccountId(int $treeAccountId): ?Safe
    {
        return Safe::query()->where('account_id', $treeAccountId)->first();
    }

    /**
     * @return array{balance_before: float, balance_after: float}
     */
    public function recordOperationalMovement(
        Safe $safe,
        float $signedAmount,
        string $details,
        string $ref,
        string $type,
        ?int $userId = null,
        ?string $date = null
    ): array {
        if (abs($signedAmount) < 0.000001) {
            return [
                'balance_before' => (float) $safe->balance,
                'balance_after' => (float) $safe->balance,
            ];
        }

        $date = $date ?? date('Y-m-d');
        $userId = $userId ?? auth()->id();

        return DB::transaction(function () use ($safe, $signedAmount, $details, $ref, $type, $userId, $date) {
            $locked = Safe::query()->lockForUpdate()->find($safe->id);
            if (! $locked) {
                throw new \RuntimeException('الخزينة غير موجودة');
            }

            $balanceBefore = (float) $locked->balance;
            $balanceAfter = round($balanceBefore + $signedAmount, 2);
            $locked->balance = $balanceAfter;
            $locked->save();

            SafeTransaction::create([
                'date' => $date,
                'type' => $signedAmount >= 0 ? 'deposit' : 'withdrawal',
                'from_safe_id' => $signedAmount >= 0 ? null : $locked->id,
                'to_safe_id' => $signedAmount >= 0 ? $locked->id : null,
                'amount' => abs($signedAmount),
                'notes' => trim($details . ($ref !== '' ? " (مرجع: {$ref})" : '')),
                'user_id' => $userId,
            ]);

            return [
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ];
        });
    }

    /**
     * مزامنة من سطر قيد محاسبي على حساب خزينة (أصول: مدين + / دائن −).
     */
    public function syncFromJournalLine(
        int $treeAccountId,
        float $debit,
        float $credit,
        string $details,
        string $ref,
        ?string $date = null
    ): void {
        $safe = $this->findByAccountId($treeAccountId);
        if (! $safe) {
            return;
        }

        $signed = round($debit - $credit, 2);
        if (abs($signed) < 0.000001) {
            return;
        }

        $this->recordOperationalMovement(
            $safe,
            $signed,
            $details,
            $ref,
            'قيود',
            null,
            $date
        );
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
        $safe = $this->findByAccountId($treeAccountId);
        if (! $safe) {
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
            $safe,
            $signed,
            $details,
            (string) $voucherId,
            'سندات',
            null,
            $date ?? date('Y-m-d')
        );
    }
}
