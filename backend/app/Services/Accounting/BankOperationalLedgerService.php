<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

/**
 * يربط رصيد البنك التشغيلي (banks + bank_details) بالقيود المحاسبية (account_entries).
 */
class BankOperationalLedgerService
{
    public const BATCH_PREFIX = 'BANK-OPS-';

    public function findByAssetId(int $treeAccountId): ?Bank
    {
        return Bank::where('asset_id', $treeAccountId)->first();
    }

    public function requireBankTreeAccountId(Bank $bank): int
    {
        if (! $bank->asset_id) {
            throw new \InvalidArgumentException('البنك غير مرتبط بحساب في شجرة الحسابات');
        }

        return (int) $bank->asset_id;
    }

    /**
     * @return array{balance_before: float, balance_after: float}
     */
    public function recordOperationalMovement(
        Bank $bank,
        float $signedAmount,
        string $details,
        string $ref,
        string $type,
        ?int $userId = null,
        ?string $date = null
    ): array {
        $date = $date ?? date('Y-m-d');
        $userId = $userId ?? auth()->id();

        $balanceBefore = (float) $bank->balance;
        $balanceAfter = $balanceBefore + $signedAmount;
        $bank->balance = $balanceAfter;
        $bank->save();

        DB::table('bank_details')->insert([
            'bank_id' => $bank->id,
            'details' => $details,
            'ref' => $ref,
            'type' => $type,
            'amount' => abs($signedAmount),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'date' => $date,
            'created_at' => now(),
            'user_id' => $userId,
        ]);

        return [
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ];
    }

    public function postDeposit(
        Bank $bank,
        float $amount,
        int $counterAccountId,
        string $description,
        string $batchCode,
        ?string $date = null
    ): void {
        $this->postPair(
            $this->requireBankTreeAccountId($bank),
            $counterAccountId,
            $amount,
            0,
            0,
            $amount,
            $description,
            $batchCode,
            $date
        );
    }

    public function postWithdraw(
        Bank $bank,
        float $amount,
        int $counterAccountId,
        string $description,
        string $batchCode,
        ?string $date = null
    ): void {
        $this->postPair(
            $this->requireBankTreeAccountId($bank),
            $counterAccountId,
            0,
            $amount,
            $amount,
            0,
            $description,
            $batchCode,
            $date
        );
    }

    public function postTransfer(Bank $fromBank, Bank $toBank, float $amount, string $description, string $batchCode, ?string $date = null): void
    {
        $fromAccountId = $this->requireBankTreeAccountId($fromBank);
        $toAccountId = $this->requireBankTreeAccountId($toBank);

        if ($fromAccountId === $toAccountId) {
            throw new \InvalidArgumentException('لا يمكن التحويل لنفس حساب البنك');
        }

        $this->createEntry($fromAccountId, 0, $amount, $description, $batchCode, $date);
        $this->createEntry($toAccountId, $amount, 0, $description, $batchCode, $date);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($fromAccountId);
        $accService->updateAccountHierarchyBalances($toAccountId);
    }

    /**
     * إيداع: يزيد رصيد البنك التشغيلي + قيد محاسبي (مدين بنك / دائن مقابل).
     */
    public function deposit(
        Bank $bank,
        float $amount,
        int $counterAccountId,
        string $reason,
        string $refType = 'ايداع',
        ?string $ref = null,
        ?string $date = null
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ الإيداع يجب أن يكون أكبر من صفر');
        }

        $this->assertDistinctAccounts($bank, $counterAccountId);
        $ref = $ref ?? $this->nextRef($refType, 'D');
        $batchCode = self::BATCH_PREFIX . $refType . '-' . $ref;
        $desc = 'إيداع بنكي - ' . $reason;

        $this->recordOperationalMovement($bank, $amount, $desc, $ref, $refType, null, $date);
        $this->postDeposit($bank, $amount, $counterAccountId, $desc, $batchCode, $date);
    }

    /**
     * سحب: ينقص رصيد البنك التشغيلي + قيد محاسبي (دائن بنك / مدين مقابل).
     */
    public function withdraw(
        Bank $bank,
        float $amount,
        int $counterAccountId,
        string $reason,
        string $refType = 'سحب',
        ?string $ref = null,
        ?string $date = null
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ السحب يجب أن يكون أكبر من صفر');
        }

        if ((float) $bank->balance < $amount) {
            throw new \InvalidArgumentException('رصيد البنك غير كافٍ');
        }

        $this->assertDistinctAccounts($bank, $counterAccountId);
        $ref = $ref ?? $this->nextRef($refType, 'W');
        $batchCode = self::BATCH_PREFIX . $refType . '-' . $ref;
        $desc = 'سحب بنكي - ' . $reason;

        $this->recordOperationalMovement($bank, -$amount, $desc, $ref, $refType, null, $date);
        $this->postWithdraw($bank, $amount, $counterAccountId, $desc, $batchCode, $date);
    }

    /**
     * تعديل الرصيد إلى قيمة مستهدفة عبر فرق محاسبي مع حساب مقابل.
     */
    public function adjustToTargetBalance(
        Bank $bank,
        float $targetBalance,
        int $counterAccountId,
        string $reason,
        ?string $date = null
    ): void {
        $current = (float) $bank->balance;
        $delta = round($targetBalance - $current, 2);

        if (abs($delta) < 0.000001) {
            return;
        }

        $ref = $this->nextRef('تعديل', 'E');
        if ($delta > 0) {
            $this->deposit($bank, $delta, $counterAccountId, $reason, 'تعديل', $ref, $date);
        } else {
            $this->withdraw($bank, abs($delta), $counterAccountId, $reason, 'تعديل', $ref, $date);
        }
    }

    /**
     * تحويل بين بنكين مع قيود محاسبية.
     */
    public function transfer(
        Bank $fromBank,
        Bank $toBank,
        float $amount,
        string $reason,
        ?string $ref = null,
        ?string $date = null
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('مبلغ التحويل يجب أن يكون أكبر من صفر');
        }

        if ($fromBank->id === $toBank->id) {
            throw new \InvalidArgumentException('لا يمكن التحويل لنفس البنك');
        }

        if ((float) $fromBank->balance < $amount) {
            throw new \InvalidArgumentException('رصيد البنك المرسل غير كافٍ');
        }

        $ref = $ref ?? $this->nextRef('تحويل', 'T');
        $batchCode = self::BATCH_PREFIX . 'TRF-' . $ref;
        $date = $date ?? date('Y-m-d');

        $fromDesc = 'تحويل إلى ' . $toBank->name . ' - ' . $reason;
        $toDesc = 'استلام من ' . $fromBank->name . ' - ' . $reason;

        $this->recordOperationalMovement($fromBank, -$amount, $fromDesc, $ref, 'تحويل', null, $date);
        $this->recordOperationalMovement($toBank, $amount, $toDesc, $ref, 'تحويل', null, $date);
        $this->postTransfer($fromBank, $toBank, $amount, $fromDesc, $batchCode, $date);
    }

    /**
     * مزامنة رصيد البنك التشغيلي من سند (القيود المحاسبية تُنشأ في VoucherController).
     */
    public function syncFromVoucher(int $bankTreeAccountId, string $voucherType, float $amount, int $voucherId, ?string $notes, ?string $date, bool $reverse): void
    {
        $bank = $this->findByAssetId($bankTreeAccountId);
        if (! $bank) {
            return;
        }

        $signed = $voucherType === 'receipt' ? $amount : -$amount;
        if ($reverse) {
            $signed *= -1;
        }

        if (abs($signed) < 0.000001) {
            return;
        }

        $details = 'سند ' . $voucherType . ' رقم ' . $voucherId . ($notes ? ' - ' . $notes : '');

        $this->recordOperationalMovement(
            $bank,
            $signed,
            $details,
            (string) $voucherId,
            'سندات',
            null,
            $date ?? date('Y-m-d')
        );
    }

    public function glBalanceForBank(Bank $bank): float
    {
        if (! $bank->asset_id) {
            return 0.0;
        }

        $row = AccountEntry::query()
            ->where('tree_account_id', $bank->asset_id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as balance')
            ->first();

        return (float) ($row->balance ?? 0);
    }

    /**
     * ترحيل قيد تصحيحي للمحاسبة فقط (بدون تغيير banks.balance) عند وجود فرق تاريخي.
     *
     * @param  float  $glGap  operational_balance − gl_balance_from_entries
     */
    public function postGlReconciliationOnly(
        Bank $bank,
        float $glGap,
        int $counterAccountId,
        string $reason,
        ?string $date = null
    ): void {
        $glGap = round($glGap, 2);
        if (abs($glGap) < 0.000001) {
            return;
        }

        $batchCode = self::BATCH_PREFIX . 'RECON-' . $bank->id . '-' . now()->format('YmdHis');
        $desc = 'مطابقة رصيد بنك مع القيود - ' . $reason;

        if ($glGap > 0) {
            $this->postDeposit($bank, $glGap, $counterAccountId, $desc, $batchCode, $date);
        } else {
            $this->postWithdraw($bank, abs($glGap), $counterAccountId, $desc, $batchCode, $date);
        }
    }

    private function postPair(
        int $bankAccountId,
        int $counterAccountId,
        float $bankDebit,
        float $bankCredit,
        float $counterDebit,
        float $counterCredit,
        string $description,
        string $batchCode,
        ?string $date
    ): void {
        if ($bankAccountId === $counterAccountId) {
            throw new \InvalidArgumentException('الحساب المقابل يجب أن يكون مختلفاً عن حساب البنك');
        }

        TreeAccount::findOrFail($bankAccountId);
        TreeAccount::findOrFail($counterAccountId);

        $this->createEntry($bankAccountId, $bankDebit, $bankCredit, $description, $batchCode, $date);
        $this->createEntry($counterAccountId, $counterDebit, $counterCredit, $description, $batchCode, $date);

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($bankAccountId);
        $accService->updateAccountHierarchyBalances($counterAccountId);
    }

    private function createEntry(
        int $treeAccountId,
        float $debit,
        float $credit,
        string $description,
        string $batchCode,
        ?string $date
    ): void {
        $date = $date ?? date('Y-m-d');

        AccountEntry::create([
            'tree_account_id' => $treeAccountId,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'entry_batch_code' => $batchCode,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    private function assertDistinctAccounts(Bank $bank, int $counterAccountId): void
    {
        $bankAccountId = $this->requireBankTreeAccountId($bank);
        if ($bankAccountId === $counterAccountId) {
            throw new \InvalidArgumentException('الحساب المقابل يجب أن يكون مختلفاً عن حساب البنك');
        }
    }

    private function nextRef(string $type, string $prefix): string
    {
        $lastRef = DB::table('bank_details')->where('type', $type)->latest('id')->first();
        if (! $lastRef || ! is_string($lastRef->ref ?? null)) {
            return $prefix . '1';
        }

        $lastNumber = (int) preg_replace('/\D/', '', $lastRef->ref);

        return $prefix . ($lastNumber + 1);
    }
}
