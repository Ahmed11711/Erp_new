<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

/**
 * مطابقة أرصدة مصادر النقد التشغيلية (خزائن / بنوك / حسابات خدمية) مع GL.
 */
class PaymentSourceReconciliationService
{
    public function __construct(
        private readonly BankOperationalLedgerService $bankLedger,
        private readonly SafeOperationalLedgerService $safeLedger,
        private readonly AccountingService $accountingService,
        private readonly ManualBalanceAdjustmentService $manualAdjustment,
    ) {}

    public function glBalanceForTreeAccount(int $treeAccountId): float
    {
        $row = AccountEntry::query()
            ->where('tree_account_id', $treeAccountId)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) AS balance')
            ->first();

        return round((float) ($row->balance ?? 0), 2);
    }

    /**
     * @return list<array{type: string, id: int, name: string, tree_account_id: int|null, tree_code: string, operational: float, gl: float, tree_stored: float|null, gap: float}>
     */
    public function collectRows(?string $typeFilter = null, ?int $idFilter = null): array
    {
        $rows = [];

        if ($typeFilter === null || $typeFilter === 'all' || $typeFilter === 'safe') {
            $q = Safe::query()->with('account:id,code,balance');
            if ($idFilter && ($typeFilter === 'safe' || $typeFilter === 'all')) {
                $q->where('id', $idFilter);
            }
            foreach ($q->orderBy('name')->get() as $safe) {
                $rows[] = $this->rowForSource(
                    'safe',
                    (int) $safe->id,
                    (string) $safe->name,
                    $safe->account_id ? (int) $safe->account_id : null,
                    $safe->account,
                    (float) $safe->balance
                );
            }
        }

        if ($typeFilter === null || $typeFilter === 'all' || $typeFilter === 'bank') {
            $q = Bank::query()->with('asset:id,code,balance');
            if ($idFilter && ($typeFilter === 'bank' || $typeFilter === 'all')) {
                $q->where('id', $idFilter);
            }
            foreach ($q->orderBy('name')->get() as $bank) {
                $rows[] = $this->rowForSource(
                    'bank',
                    (int) $bank->id,
                    (string) $bank->name,
                    $bank->asset_id ? (int) $bank->asset_id : null,
                    $bank->asset,
                    (float) $bank->balance
                );
            }
        }

        if ($typeFilter === null || $typeFilter === 'all' || $typeFilter === 'service_account') {
            $q = ServiceAccount::query()->with('account:id,code,balance');
            if ($idFilter && ($typeFilter === 'service_account' || $typeFilter === 'all')) {
                $q->where('id', $idFilter);
            }
            foreach ($q->orderBy('name')->get() as $svc) {
                $rows[] = $this->rowForSource(
                    'service_account',
                    (int) $svc->id,
                    (string) $svc->name,
                    $svc->account_id ? (int) $svc->account_id : null,
                    $svc->account,
                    (float) $svc->balance
                );
            }
        }

        return $rows;
    }

    /**
     * @return array{type: string, id: int, name: string, tree_account_id: int|null, tree_code: string, operational: float, gl: float, tree_stored: float|null, gap: float}
     */
    private function rowForSource(
        string $type,
        int $id,
        string $name,
        ?int $treeAccountId,
        ?TreeAccount $treeAccount,
        float $operational
    ): array {
        $gl = $treeAccountId ? $this->glBalanceForTreeAccount($treeAccountId) : 0.0;
        $treeStored = $treeAccount ? round((float) $treeAccount->balance, 2) : null;
        $operational = round($operational, 2);
        $gap = round($operational - $gl, 2);

        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'tree_account_id' => $treeAccountId,
            'tree_code' => $treeAccount ? (string) $treeAccount->code : '—',
            'operational' => $operational,
            'gl' => $gl,
            'tree_stored' => $treeStored,
            'gap' => $gap,
        ];
    }

    /**
     * ضبط الرصيد التشغيلي ليطابق GL (المحاسبة مصدر الحقيقة).
     *
     * @return array{before: float, after: float, gl: float}
     */
    public function syncOperationalToGl(string $type, int $id, string $reason): array
    {
        $row = $this->findRow($type, $id);
        if (! $row['tree_account_id']) {
            throw new \InvalidArgumentException('المصدر غير مرتبط بحساب في شجرة الحسابات.');
        }

        $targetGl = $row['gl'];
        $before = $row['operational'];
        $delta = round($targetGl - $before, 2);

        if (abs($delta) < 0.009) {
            return ['before' => $before, 'after' => $before, 'gl' => $targetGl];
        }

        return DB::transaction(function () use ($type, $id, $reason, $targetGl, $before, $delta) {
            if ($type === 'safe') {
                $safe = Safe::query()->lockForUpdate()->findOrFail($id);
                $this->safeLedger->recordOperationalMovement(
                    $safe,
                    $delta,
                    'مطابقة رصيد مع GL — ' . $reason,
                    'RECON-OPS',
                    'مطابقة',
                    null,
                    date('Y-m-d')
                );
            } elseif ($type === 'bank') {
                $bank = Bank::query()->lockForUpdate()->findOrFail($id);
                $this->bankLedger->recordOperationalMovement(
                    $bank,
                    $delta,
                    'مطابقة رصيد مع GL — ' . $reason,
                    'RECON-OPS',
                    'تعديل',
                    null,
                    date('Y-m-d')
                );
            } else {
                $svc = ServiceAccount::query()->lockForUpdate()->findOrFail($id);
                if ($delta >= 0) {
                    ServiceAccount::query()->whereKey($svc->id)->increment('balance', $delta);
                } else {
                    ServiceAccount::query()->whereKey($svc->id)->decrement('balance', abs($delta));
                }
            }

            return ['before' => $before, 'after' => $targetGl, 'gl' => $targetGl];
        });
    }

    /**
     * ترحيل قيد تصحيحي GL ليطابق الرصيد التشغيلي (بدون تغيير الرصيد التشغيلي).
     */
    public function syncGlToOperational(string $type, int $id, int $counterAccountId, string $reason): void
    {
        $row = $this->findRow($type, $id);
        if (! $row['tree_account_id']) {
            throw new \InvalidArgumentException('المصدر غير مرتبط بحساب في شجرة الحسابات.');
        }

        $gap = $row['gap'];
        if (abs($gap) < 0.009) {
            return;
        }

        if ($type === 'bank') {
            $bank = Bank::findOrFail($id);
            $this->bankLedger->postGlReconciliationOnly(
                $bank,
                $gap,
                $counterAccountId,
                $reason
            );
            $this->accountingService->updateAccountHierarchyBalances((int) $row['tree_account_id']);
            $this->accountingService->updateAccountHierarchyBalances($counterAccountId);

            return;
        }

        $assetAccountId = (int) $row['tree_account_id'];
        $amount = abs($gap);
        $batchCode = 'RECON-GL-' . strtoupper($type) . '-' . $id . '-' . now()->format('YmdHis');
        $desc = 'مطابقة GL مع الرصيد التشغيلي — ' . $reason;

        DB::transaction(function () use ($gap, $assetAccountId, $counterAccountId, $amount, $batchCode, $desc) {
            $entryNumber = DailyEntry::getNextEntryNumber();
            $dailyEntry = DailyEntry::create([
                'date' => now(),
                'entry_number' => $entryNumber,
                'description' => $desc,
                'user_id' => 1,
            ]);

            if ($gap > 0) {
                $lines = [
                    ['account_id' => $assetAccountId, 'debit' => $amount, 'credit' => 0, 'note' => $desc],
                    ['account_id' => $counterAccountId, 'debit' => 0, 'credit' => $amount, 'note' => $desc],
                ];
                $aeLines = [
                    [$assetAccountId, $amount, 0],
                    [$counterAccountId, 0, $amount],
                ];
            } else {
                $lines = [
                    ['account_id' => $assetAccountId, 'debit' => 0, 'credit' => $amount, 'note' => $desc],
                    ['account_id' => $counterAccountId, 'debit' => $amount, 'credit' => 0, 'note' => $desc],
                ];
                $aeLines = [
                    [$assetAccountId, 0, $amount],
                    [$counterAccountId, $amount, 0],
                ];
            }

            foreach ($lines as $line) {
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'notes' => $line['note'],
                ]);
            }

            foreach ($aeLines as [$accId, $dr, $cr]) {
                AccountEntry::create([
                    'tree_account_id' => $accId,
                    'debit' => $dr,
                    'credit' => $cr,
                    'description' => $desc,
                    'entry_batch_code' => $batchCode,
                    'daily_entry_id' => $dailyEntry->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->accountingService->updateAccountHierarchyBalances($assetAccountId);
            $this->accountingService->updateAccountHierarchyBalances($counterAccountId);
        });
    }

    /**
     * @return array{type: string, id: int, name: string, tree_account_id: int|null, tree_code: string, operational: float, gl: float, tree_stored: float|null, gap: float}
     */
    private function findRow(string $type, int $id): array
    {
        $rows = $this->collectRows($type, $id);
        $row = $rows[0] ?? null;
        if (! $row) {
            throw new \InvalidArgumentException("لم يُعثر على مصدر من النوع {$type} برقم {$id}.");
        }

        return $row;
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'safe' => 'خزينة',
            'bank' => 'بنك',
            'service_account' => 'حساب خدمي',
            default => $type,
        };
    }

    /**
     * ضبط الرصيد الفعلي (تشغيلي + GL) عبر قيد يومي افتتاحي/تسوية.
     *
     * @return array{
     *   daily_entry_id: int,
     *   previous_gl: float,
     *   previous_operational: float,
     *   target_balance: float,
     *   delta_gl: float,
     *   operational_after: float
     * }
     */
    public function setTargetBalance(
        string $type,
        int $id,
        float $targetBalance,
        TreeAccount $counterAccount,
        string $dateYmd,
        string $reason,
        int $userId
    ): array {
        $row = $this->findRow($type, $id);
        if (! $row['tree_account_id']) {
            throw new \InvalidArgumentException('المصدر غير مرتبط بحساب في شجرة الحسابات.');
        }

        $treeAccount = TreeAccount::findOrFail((int) $row['tree_account_id']);
        $targetBalance = round($targetBalance, 2);
        $operationalBefore = $row['operational'];

        return DB::transaction(function () use (
            $type,
            $id,
            $treeAccount,
            $counterAccount,
            $targetBalance,
            $dateYmd,
            $reason,
            $userId,
            $operationalBefore
        ) {
            $glResult = $this->manualAdjustment->adjustToTarget(
                $treeAccount,
                $targetBalance,
                $counterAccount,
                $reason,
                $dateYmd,
                $userId
            );

            $opsResult = $this->syncOperationalToGl($type, $id, $reason);

            return [
                'daily_entry_id' => (int) $glResult['daily_entry']->id,
                'previous_gl' => $glResult['previous_net'],
                'previous_operational' => $operationalBefore,
                'target_balance' => $targetBalance,
                'delta_gl' => $glResult['delta'],
                'operational_after' => $opsResult['after'],
            ];
        });
    }

    public function suggestCounterAccounts(): \Illuminate\Support\Collection
    {
        return TreeAccount::query()
            ->whereIn('type', ['equity', 'liability'])
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);
    }
}
