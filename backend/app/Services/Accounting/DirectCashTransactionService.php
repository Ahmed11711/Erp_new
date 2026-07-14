<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\Safe;
use App\Models\SafeTransaction;
use App\Models\TreeAccount;
use Illuminate\Support\Facades\DB;

class DirectCashTransactionService
{
    public const BANK_BATCH_PREFIX = 'BANK-DW-';

    public const SAFE_BATCH_PREFIX = 'SAFE-DW-';

    public function listBankDirect(array $filters = [])
    {
        $query = BankTransaction::query()
            ->with(['counterAccount:id,name,code', 'user:id,name'])
            ->whereIn('type', ['deposit', 'withdrawal'])
            ->where(function ($q) {
                $q->whereNotNull('counter_account_id')
                    ->orWhereNotNull('entry_batch_code');
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (! empty($filters['bank_id'])) {
            $bankId = (int) $filters['bank_id'];
            $query->where(function ($q) use ($bankId) {
                $q->where('from_bank_id', $bankId)->orWhere('to_bank_id', $bankId);
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 25);

        return $query->paginate($perPage)->through(fn (BankTransaction $txn) => $this->formatBankRow($txn));
    }

    public function showBankDirect(int $id): array
    {
        $txn = $this->findEditableBankTransaction($id);

        return $this->formatBankRow($txn);
    }

    public function createBank(array $data): BankTransaction
    {
        return DB::transaction(function () use ($data) {
            $bank = Bank::findOrFail($data['bank_id']);
            $counterAccount = TreeAccount::findOrFail($data['counter_account_id']);
            $amount = (float) $data['amount'];
            $storageType = $this->apiTypeToStorage($data['type']);
            $date = $data['date'];
            $notes = $data['notes'] ?? '';

            if (! $bank->asset_id) {
                throw new \InvalidArgumentException('البنك غير مرتبط بحساب شجري');
            }

            if ($storageType === 'withdrawal' && (float) $bank->balance < $amount) {
                throw new \InvalidArgumentException('رصيد البنك غير كافي للسحب');
            }

            $txn = BankTransaction::create([
                'date' => $date,
                'type' => $storageType,
                'from_bank_id' => $storageType === 'withdrawal' ? $bank->id : null,
                'to_bank_id' => $storageType === 'deposit' ? $bank->id : null,
                'counter_account_id' => $counterAccount->id,
                'amount' => $amount,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            $batchCode = self::BANK_BATCH_PREFIX . $txn->id;
            $txn->update(['entry_batch_code' => $batchCode]);

            $this->postBankEntries($bank, $counterAccount, $storageType, $amount, $date, $notes, $batchCode);
            $this->applyBankBalanceDelta($bank, $storageType, $amount, $date, $notes, $batchCode);
            app(CustomerCompanyDirectCashSyncService::class)->syncBankTransaction(
                $bank,
                $counterAccount,
                $storageType,
                $amount,
                $date,
                $notes,
                $batchCode
            );

            return $txn->fresh(['counterAccount', 'user']);
        });
    }

    public function updateBankDirect(int $id, array $data): BankTransaction
    {
        return DB::transaction(function () use ($id, $data) {
            $txn = $this->findEditableBankTransaction($id);

            if (! $txn->entry_batch_code) {
                throw new \InvalidArgumentException('لا يمكن تعديل هذه العملية — سجّل غير مرتبط بالقيود');
            }

            $oldBank = $this->resolveBankFromTransaction($txn);
            $oldCounterId = (int) $txn->counter_account_id;
            $oldStorageType = $txn->type;
            $oldAmount = (float) $txn->amount;

            $newBank = Bank::findOrFail($data['bank_id']);
            $newCounter = TreeAccount::findOrFail($data['counter_account_id']);
            $newAmount = (float) $data['amount'];
            $newStorageType = $this->apiTypeToStorage($data['type']);
            $newDate = $data['date'];
            $newNotes = $data['notes'] ?? '';

            if (! $newBank->asset_id) {
                throw new \InvalidArgumentException('البنك غير مرتبط بحساب شجري');
            }

            if ($newBank->id === $oldBank->id) {
                $balanceAfterUndo = $this->projectedBankBalanceAfterUndo($oldBank, $oldStorageType, $oldAmount);
            } else {
                $balanceAfterUndo = (float) $newBank->balance;
            }

            if ($newStorageType === 'withdrawal') {
                $available = $newBank->id === $oldBank->id
                    ? $balanceAfterUndo
                    : (float) $newBank->fresh()->balance;
                if ($available < $newAmount) {
                    throw new \InvalidArgumentException('رصيد البنك غير كافي للسحب');
                }
            }

            $syncService = app(CustomerCompanyDirectCashSyncService::class);
            $syncService->reverseByRef($txn->entry_batch_code);

            $affectedAccounts = $this->removeActiveEntriesByBatch($txn->entry_batch_code);

            $bankLedger = app(BankOperationalLedgerService::class);
            $hadOperationalDetail = $bankLedger->removeOperationalDetailsByRef($oldBank, $txn->entry_batch_code);
            if (! $hadOperationalDetail) {
                $this->undoBankBalanceDelta($oldBank, $oldStorageType, $oldAmount);
            }

            $txn->update([
                'date' => $newDate,
                'type' => $newStorageType,
                'from_bank_id' => $newStorageType === 'withdrawal' ? $newBank->id : null,
                'to_bank_id' => $newStorageType === 'deposit' ? $newBank->id : null,
                'counter_account_id' => $newCounter->id,
                'amount' => $newAmount,
                'notes' => $newNotes,
            ]);

            $newBank->refresh();

            $this->postBankEntries($newBank, $newCounter, $newStorageType, $newAmount, $newDate, $newNotes, $txn->entry_batch_code);
            $this->applyBankBalanceDelta($newBank, $newStorageType, $newAmount, $newDate, $newNotes, $txn->entry_batch_code);
            $syncService->syncBankTransaction(
                $newBank,
                $newCounter,
                $newStorageType,
                $newAmount,
                $newDate,
                $newNotes,
                $txn->entry_batch_code
            );

            $affectedAccounts[] = $newBank->asset_id;
            $affectedAccounts[] = $oldBank->asset_id;
            $affectedAccounts[] = $newCounter->id;
            $affectedAccounts[] = $oldCounterId;

            $accService = app(AccountingService::class);
            foreach (array_unique(array_filter($affectedAccounts)) as $accountId) {
                $accService->updateAccountHierarchyBalances($accountId);
            }

            return $txn->fresh(['counterAccount', 'user']);
        });
    }

    public function listSafeDirect(array $filters = [])
    {
        $query = SafeTransaction::query()
            ->with(['counterAccount:id,name,code', 'user:id,name'])
            ->whereIn('type', ['deposit', 'withdrawal'])
            ->where(function ($q) {
                $q->whereNotNull('counter_account_id')
                    ->orWhereNotNull('entry_batch_code');
            })
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (! empty($filters['safe_id'])) {
            $safeId = (int) $filters['safe_id'];
            $query->where(function ($q) use ($safeId) {
                $q->where('from_safe_id', $safeId)->orWhere('to_safe_id', $safeId);
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        $perPage = (int) ($filters['per_page'] ?? 25);

        return $query->paginate($perPage)->through(fn (SafeTransaction $txn) => $this->formatSafeRow($txn));
    }

    public function showSafeDirect(int $id): array
    {
        $txn = $this->findEditableSafeTransaction($id);

        return $this->formatSafeRow($txn);
    }

    public function createSafe(array $data): SafeTransaction
    {
        return DB::transaction(function () use ($data) {
            $safe = Safe::findOrFail($data['safe_id']);
            $counterAccount = TreeAccount::findOrFail($data['counter_account_id']);
            $amount = (float) $data['amount'];
            $storageType = $this->apiTypeToStorage($data['type']);
            $date = $data['date'];
            $notes = $data['notes'] ?? '';

            if (! $safe->account_id) {
                throw new \InvalidArgumentException('الخزينة غير مرتبطة بحساب شجري');
            }

            if ($storageType === 'withdrawal' && (float) $safe->balance < $amount) {
                throw new \InvalidArgumentException('رصيد الخزينة غير كافي');
            }

            $txn = SafeTransaction::create([
                'date' => $date,
                'type' => $storageType,
                'from_safe_id' => $storageType === 'withdrawal' ? $safe->id : null,
                'to_safe_id' => $storageType === 'deposit' ? $safe->id : null,
                'counter_account_id' => $counterAccount->id,
                'amount' => $amount,
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            $batchCode = self::SAFE_BATCH_PREFIX . $txn->id;
            $txn->update(['entry_batch_code' => $batchCode]);

            $this->postSafeEntries($safe, $counterAccount, $storageType, $amount, $date, $notes, $batchCode);
            $this->applySafeBalanceDelta($safe, $storageType, $amount);
            $this->syncSafeCustomerCompany($safe, $counterAccount, $storageType, $amount, $date, $notes, $batchCode);

            return $txn->fresh(['counterAccount', 'user']);
        });
    }

    public function updateSafeDirect(int $id, array $data): SafeTransaction
    {
        return DB::transaction(function () use ($id, $data) {
            $txn = $this->findEditableSafeTransaction($id);

            if (! $txn->entry_batch_code) {
                throw new \InvalidArgumentException('لا يمكن تعديل هذه العملية — سجّل غير مرتبط بالقيود');
            }

            $oldSafe = $this->resolveSafeFromTransaction($txn);
            $oldCounterId = (int) $txn->counter_account_id;
            $oldStorageType = $txn->type;
            $oldAmount = (float) $txn->amount;

            $newSafe = Safe::findOrFail($data['safe_id']);
            $newCounter = TreeAccount::findOrFail($data['counter_account_id']);
            $newAmount = (float) $data['amount'];
            $newStorageType = $this->apiTypeToStorage($data['type']);
            $newDate = $data['date'];
            $newNotes = $data['notes'] ?? '';

            if (! $newSafe->account_id) {
                throw new \InvalidArgumentException('الخزينة غير مرتبطة بحساب شجري');
            }

            if ($newSafe->id === $oldSafe->id) {
                $balanceAfterUndo = $this->projectedSafeBalanceAfterUndo($oldSafe, $oldStorageType, $oldAmount);
            } else {
                $this->undoSafeBalanceDelta($oldSafe, $oldStorageType, $oldAmount);
                $balanceAfterUndo = null;
            }

            if ($newStorageType === 'withdrawal') {
                $available = $newSafe->id === $oldSafe->id
                    ? $balanceAfterUndo
                    : (float) $newSafe->fresh()->balance;
                if ($available < $newAmount) {
                    throw new \InvalidArgumentException('رصيد الخزينة غير كافي');
                }
            }

            $syncService = app(CustomerCompanyDirectCashSyncService::class);
            $syncService->reverseByRef($txn->entry_batch_code);

            $affectedAccounts = $this->removeActiveEntriesByBatch($txn->entry_batch_code);

            if ($newSafe->id === $oldSafe->id) {
                $this->undoSafeBalanceDelta($oldSafe, $oldStorageType, $oldAmount);
            }

            $txn->update([
                'date' => $newDate,
                'type' => $newStorageType,
                'from_safe_id' => $newStorageType === 'withdrawal' ? $newSafe->id : null,
                'to_safe_id' => $newStorageType === 'deposit' ? $newSafe->id : null,
                'counter_account_id' => $newCounter->id,
                'amount' => $newAmount,
                'notes' => $newNotes,
            ]);

            $this->postSafeEntries($newSafe, $newCounter, $newStorageType, $newAmount, $newDate, $newNotes, $txn->entry_batch_code);
            $this->applySafeBalanceDelta($newSafe, $newStorageType, $newAmount);
            $this->syncSafeCustomerCompany($newSafe, $newCounter, $newStorageType, $newAmount, $newDate, $newNotes, $txn->entry_batch_code);

            $affectedAccounts[] = $newSafe->account_id;
            $affectedAccounts[] = $oldSafe->account_id;
            $affectedAccounts[] = $newCounter->id;
            $affectedAccounts[] = $oldCounterId;

            $accService = app(AccountingService::class);
            foreach (array_unique(array_filter($affectedAccounts)) as $accountId) {
                $accService->updateAccountHierarchyBalances($accountId);
            }

            return $txn->fresh(['counterAccount', 'user']);
        });
    }

    private function syncSafeCustomerCompany(
        Safe $safe,
        TreeAccount $counterAccount,
        string $storageType,
        float $amount,
        string $date,
        string $notes,
        string $batchCode
    ): void {
        $syncService = app(CustomerCompanyDirectCashSyncService::class);
        $details = $syncService->buildCashLabel('safe', $storageType, $batchCode, $notes);
        $syncService->syncIfCustomerCompanyTreeAccount(
            $counterAccount,
            $storageType,
            $amount,
            null,
            $batchCode,
            $details,
            $date
        );
    }

    private function findEditableBankTransaction(int $id): BankTransaction
    {
        $txn = BankTransaction::with(['counterAccount', 'user'])->find($id);
        if (! $txn || ! in_array($txn->type, ['deposit', 'withdrawal'], true)) {
            throw new \InvalidArgumentException('عملية السحب/الإيداع غير موجودة');
        }

        return $txn;
    }

    private function findEditableSafeTransaction(int $id): SafeTransaction
    {
        $txn = SafeTransaction::with(['counterAccount', 'user'])->find($id);
        if (! $txn || ! in_array($txn->type, ['deposit', 'withdrawal'], true)) {
            throw new \InvalidArgumentException('عملية السحب/الإيداع غير موجودة');
        }

        return $txn;
    }

    private function formatBankRow(BankTransaction $txn): array
    {
        $bankId = $txn->to_bank_id ?? $txn->from_bank_id;
        $bank = $bankId ? Bank::find($bankId) : null;

        return [
            'id' => $txn->id,
            'bank_id' => $bankId,
            'bank_name' => $bank?->name,
            'type' => $this->storageTypeToApi($txn->type),
            'counter_account_id' => $txn->counter_account_id,
            'counter_account_name' => $txn->counterAccount?->name,
            'counter_account_code' => $txn->counterAccount?->code,
            'amount' => (float) $txn->amount,
            'date' => $txn->date?->format('Y-m-d'),
            'notes' => $txn->notes,
            'entry_batch_code' => $txn->entry_batch_code,
            'editable' => (bool) $txn->entry_batch_code,
            'user_name' => $txn->user?->name,
            'created_at' => $txn->created_at?->toDateTimeString(),
        ];
    }

    private function formatSafeRow(SafeTransaction $txn): array
    {
        $safeId = $txn->to_safe_id ?? $txn->from_safe_id;
        $safe = $safeId ? Safe::find($safeId) : null;

        return [
            'id' => $txn->id,
            'safe_id' => $safeId,
            'safe_name' => $safe?->name,
            'type' => $this->storageTypeToApi($txn->type),
            'counter_account_id' => $txn->counter_account_id,
            'counter_account_name' => $txn->counterAccount?->name,
            'counter_account_code' => $txn->counterAccount?->code,
            'amount' => (float) $txn->amount,
            'date' => $txn->date?->format('Y-m-d'),
            'notes' => $txn->notes,
            'entry_batch_code' => $txn->entry_batch_code,
            'editable' => (bool) $txn->entry_batch_code,
            'user_name' => $txn->user?->name,
            'created_at' => $txn->created_at?->toDateTimeString(),
        ];
    }

    private function apiTypeToStorage(string $type): string
    {
        return $type === 'receipt' ? 'deposit' : 'withdrawal';
    }

    private function storageTypeToApi(string $type): string
    {
        return $type === 'deposit' ? 'receipt' : 'payment';
    }

    private function resolveBankFromTransaction(BankTransaction $txn): Bank
    {
        $bankId = $txn->to_bank_id ?? $txn->from_bank_id;

        return Bank::findOrFail($bankId);
    }

    private function resolveSafeFromTransaction(SafeTransaction $txn): Safe
    {
        $safeId = $txn->to_safe_id ?? $txn->from_safe_id;

        return Safe::findOrFail($safeId);
    }

    private function projectedBankBalanceAfterUndo(Bank $bank, string $storageType, float $amount): float
    {
        $balance = (float) $bank->balance;

        return $storageType === 'deposit'
            ? $balance - $amount
            : $balance + $amount;
    }

    private function projectedSafeBalanceAfterUndo(Safe $safe, string $storageType, float $amount): float
    {
        $balance = (float) $safe->balance;

        return $storageType === 'deposit'
            ? $balance - $amount
            : $balance + $amount;
    }

    private function applyBankBalanceDelta(
        Bank $bank,
        string $storageType,
        float $amount,
        ?string $date = null,
        ?string $notes = null,
        ?string $batchCode = null
    ): void {
        $signed = $storageType === 'deposit' ? $amount : -$amount;
        $label = $storageType === 'deposit' ? 'إيداع بنكي' : 'سحب بنكي';
        $details = $label . ($batchCode ? " [{$batchCode}]" : '') . ($notes ? ' - ' . $notes : '');

        app(BankOperationalLedgerService::class)->recordOperationalMovement(
            $bank,
            $signed,
            $details,
            $batchCode ?? '-',
            $storageType === 'deposit' ? 'ايداع' : 'سحب',
            auth()->id(),
            $date ?? date('Y-m-d')
        );
    }

    private function undoBankBalanceDelta(Bank $bank, string $storageType, float $amount): void
    {
        if ($storageType === 'deposit') {
            $bank->decrement('balance', $amount);
        } else {
            $bank->increment('balance', $amount);
        }
    }

    private function applySafeBalanceDelta(Safe $safe, string $storageType, float $amount): void
    {
        if ($storageType === 'deposit') {
            $safe->increment('balance', $amount);
        } else {
            $safe->decrement('balance', $amount);
        }
    }

    private function undoSafeBalanceDelta(Safe $safe, string $storageType, float $amount): void
    {
        if ($storageType === 'deposit') {
            $safe->decrement('balance', $amount);
        } else {
            $safe->increment('balance', $amount);
        }
    }

    /**
     * @return int[] affected tree account ids (before removal)
     */
    private function removeActiveEntriesByBatch(string $batchCode): array
    {
        $entries = AccountEntry::where('entry_batch_code', $batchCode)->get();
        $affected = $entries->pluck('tree_account_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        AccountEntry::where('entry_batch_code', $batchCode)->delete();

        return $affected;
    }

    private function postBankEntries(
        Bank $bank,
        TreeAccount $counter,
        string $storageType,
        float $amount,
        string $date,
        string $notes,
        string $batchCode
    ): void {
        $bankAccountId = (int) $bank->asset_id;
        $label = $storageType === 'deposit' ? 'إيداع بنكي' : 'سحب بنكي';
        $desc = "{$label} [{$batchCode}]" . ($notes !== '' ? ' - ' . $notes : '');

        if ($storageType === 'deposit') {
            $this->createEntry($bankAccountId, $amount, 0, $desc, $batchCode, $date);
            $this->createEntry($counter->id, 0, $amount, $desc, $batchCode, $date);
        } else {
            $this->createEntry($bankAccountId, 0, $amount, $desc, $batchCode, $date);
            $this->createEntry($counter->id, $amount, 0, $desc, $batchCode, $date);
        }

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($bankAccountId);
        $accService->updateAccountHierarchyBalances($counter->id);
    }

    private function postSafeEntries(
        Safe $safe,
        TreeAccount $counter,
        string $storageType,
        float $amount,
        string $date,
        string $notes,
        string $batchCode
    ): void {
        $safeAccountId = (int) $safe->account_id;
        $label = $storageType === 'deposit' ? 'إيداع خزينة' : 'صرف خزينة';
        $desc = "{$label} [{$batchCode}]" . ($notes !== '' ? ' - ' . $notes : '');

        if ($storageType === 'deposit') {
            $this->createEntry($safeAccountId, $amount, 0, $desc, $batchCode, $date);
            $this->createEntry($counter->id, 0, $amount, $desc, $batchCode, $date);
        } else {
            $this->createEntry($safeAccountId, 0, $amount, $desc, $batchCode, $date);
            $this->createEntry($counter->id, $amount, 0, $desc, $batchCode, $date);
        }

        $accService = app(AccountingService::class);
        $accService->updateAccountHierarchyBalances($safeAccountId);
        $accService->updateAccountHierarchyBalances($counter->id);
    }

    private function createEntry(
        int $treeAccountId,
        float $debit,
        float $credit,
        string $description,
        string $batchCode,
        string $date
    ): void {
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
}
