<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\SafeTransaction;
use Illuminate\Support\Collection;

/**
 * يربط سطر كشف الحساب (account_entries) بمصدره القابل للفتح/التعديل (للمدير فقط).
 */
class AccountEntryEditLinkService
{
    /** @var array<string, int|null> */
    private array $expenseIdByBatch = [];

    /** @var array<int, bool> */
    private array $bankDirectExists = [];

    /** @var array<int, bool> */
    private array $safeDirectExists = [];

    /**
     * @param  Collection<int, AccountEntry>  $entries
     */
    public function attachEditLinks(Collection $entries, bool $forAdmin): void
    {
        if (! $forAdmin || $entries->isEmpty()) {
            foreach ($entries as $entry) {
                $entry->setAttribute('can_edit', false);
                $entry->setAttribute('edit_link', null);
            }

            return;
        }

        $this->warmCaches($entries);

        foreach ($entries as $entry) {
            $link = $this->resolve($entry);
            $entry->setAttribute('can_edit', $link !== null);
            $entry->setAttribute('edit_link', $link);
        }
    }

    /**
     * @param  Collection<int, AccountEntry>  $entries
     */
    private function warmCaches(Collection $entries): void
    {
        $batchCodes = $entries->pluck('entry_batch_code')->filter()->unique()->values()->all();

        $expenseBatches = array_values(array_filter(
            $batchCodes,
            fn ($code) => is_string($code) && str_starts_with($code, 'EXP-') && ! str_starts_with($code, 'EXP-REV-')
        ));

        if ($expenseBatches !== []) {
            $batchRows = AccountEntry::query()
                ->whereIn('entry_batch_code', $expenseBatches)
                ->select('entry_batch_code', 'description')
                ->get()
                ->groupBy('entry_batch_code');

            foreach ($batchRows as $batchCode => $rows) {
                foreach ($rows as $row) {
                    $expenseId = $this->findActiveExpenseIdFromText((string) $row->description);
                    if ($expenseId !== null) {
                        $this->expenseIdByBatch[(string) $batchCode] = $expenseId;
                        break;
                    }
                }
            }
        }

        $bankIds = [];
        $safeIds = [];
        foreach ($batchCodes as $code) {
            if (! is_string($code)) {
                continue;
            }
            if (str_starts_with($code, DirectCashTransactionService::BANK_BATCH_PREFIX)) {
                $bankIds[] = (int) substr($code, strlen(DirectCashTransactionService::BANK_BATCH_PREFIX));
            } elseif (str_starts_with($code, DirectCashTransactionService::SAFE_BATCH_PREFIX)) {
                $safeIds[] = (int) substr($code, strlen(DirectCashTransactionService::SAFE_BATCH_PREFIX));
            }
        }

        if ($bankIds !== []) {
            $existing = BankTransaction::query()->whereIn('id', array_unique($bankIds))->pluck('id')->all();
            foreach ($existing as $id) {
                $this->bankDirectExists[(int) $id] = true;
            }
        }

        if ($safeIds !== []) {
            $existing = SafeTransaction::query()->whereIn('id', array_unique($safeIds))->pluck('id')->all();
            foreach ($existing as $id) {
                $this->safeDirectExists[(int) $id] = true;
            }
        }
    }

    /**
     * @return array{source: string, source_id: int, url: string}|null
     */
    public function resolve(AccountEntry $entry): ?array
    {
        if ($entry->voucher_id) {
            return $this->link('voucher', (int) $entry->voucher_id, '/dashboard/financial/cash/previous', [
                'edit_voucher' => (int) $entry->voucher_id,
            ]);
        }

        $orderId = $this->resolveOrderId($entry);
        if ($orderId) {
            $query = $this->isPrepaidOrderEntry($entry) ? ['prepaid_adjust' => 1] : [];

            return $this->link('order', $orderId, '/dashboard/shipping/orderdetails/'.$orderId, $query);
        }

        if ($entry->daily_entry_id) {
            return $this->link('daily_entry', (int) $entry->daily_entry_id, '/dashboard/accounting/daily-entries', [
                'edit' => (int) $entry->daily_entry_id,
            ]);
        }

        if ($entry->cimmitment_id) {
            return $this->link('cimmitment', (int) $entry->cimmitment_id, '/dashboard/financial/discounts', [
                'edit' => (int) $entry->cimmitment_id,
            ]);
        }

        $expenseLink = $this->resolveExpenseLink($entry);
        if ($expenseLink !== null) {
            return $expenseLink;
        }

        $batch = (string) ($entry->entry_batch_code ?? '');
        if ($batch !== '') {
            if (str_starts_with($batch, DirectCashTransactionService::BANK_BATCH_PREFIX)) {
                $txnId = (int) substr($batch, strlen(DirectCashTransactionService::BANK_BATCH_PREFIX));
                if ($txnId > 0 && ($this->bankDirectExists[$txnId] ?? BankTransaction::query()->whereKey($txnId)->exists())) {
                    return $this->link('bank_direct', $txnId, '/dashboard/accounting/banks/deposit-withdraw', [
                        'edit' => $txnId,
                    ]);
                }
            }

            if (str_starts_with($batch, DirectCashTransactionService::SAFE_BATCH_PREFIX)) {
                $txnId = (int) substr($batch, strlen(DirectCashTransactionService::SAFE_BATCH_PREFIX));
                if ($txnId > 0 && ($this->safeDirectExists[$txnId] ?? SafeTransaction::query()->whereKey($txnId)->exists())) {
                    return $this->link('safe_direct', $txnId, '/dashboard/accounting/safes/deposit-withdraw', [
                        'edit' => $txnId,
                    ]);
                }
            }
        }

        $description = (string) ($entry->description ?? '');

        if (preg_match('/\['.preg_quote(DirectCashTransactionService::BANK_BATCH_PREFIX, '/').'(\d+)\]/', $description, $matches)) {
            $txnId = (int) $matches[1];
            if ($txnId > 0 && ($this->bankDirectExists[$txnId] ?? BankTransaction::query()->whereKey($txnId)->exists())) {
                return $this->link('bank_direct', $txnId, '/dashboard/accounting/banks/deposit-withdraw', [
                    'edit' => $txnId,
                ]);
            }
        }

        return null;
    }

    /**
     * @return array{source: string, source_id: int, url: string}|null
     */
    private function resolveExpenseLink(AccountEntry $entry): ?array
    {
        $batch = (string) ($entry->entry_batch_code ?? '');
        if ($batch !== '' && str_starts_with($batch, 'EXP-') && ! str_starts_with($batch, 'EXP-REV-')) {
            $expenseId = $this->expenseIdByBatch[$batch] ?? null;
            if ($expenseId !== null) {
                return $this->link('expense', $expenseId, '/dashboard/financial/editexpense/'.$expenseId);
            }
        }

        $expenseId = $this->findActiveExpenseIdFromText((string) ($entry->description ?? ''));
        if ($expenseId !== null) {
            return $this->link('expense', $expenseId, '/dashboard/financial/editexpense/'.$expenseId);
        }

        return null;
    }

    private function findActiveExpenseIdFromText(string $text): ?int
    {
        $expenseNumber = $this->extractExpenseNumber($text);
        if ($expenseNumber === null) {
            return null;
        }

        if (preg_match('/EX(\d+)/', $expenseNumber, $matches)) {
            $byId = Expense::query()
                ->whereKey((int) $matches[1])
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', '!=', 1);
                })
                ->value('id');
            if ($byId) {
                return (int) $byId;
            }
        }

        $byNumber = Expense::query()
            ->where('expense_number', $expenseNumber)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 1);
            })
            ->value('id');

        return $byNumber ? (int) $byNumber : null;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return array{source: string, source_id: int, url: string}
     */
    private function link(string $source, int $sourceId, string $path, array $query = []): array
    {
        $url = $path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        return [
            'source' => $source,
            'source_id' => $sourceId,
            'url' => $url,
        ];
    }

    private function resolveOrderId(AccountEntry $entry): ?int
    {
        if ($entry->order_id) {
            return (int) $entry->order_id;
        }

        $batch = (string) ($entry->entry_batch_code ?? '');
        if ($batch === '') {
            return $this->parseOrderIdFromDescription((string) ($entry->description ?? ''));
        }

        if (str_starts_with($batch, OrderPaymentSourceLedgerService::BATCH_PREFIX)) {
            return $this->parseOrderIdFromOrdOpsBatch($batch);
        }

        foreach (['ORD-PREPAID-PENDING-', 'ORD-PREPAID-', 'PARTCOLLECT-', 'PREPAID-REV-'] as $prefix) {
            if (str_starts_with($batch, $prefix)) {
                $suffix = substr($batch, strlen($prefix));
                $orderId = (int) strtok($suffix, '-');

                return $orderId > 0 ? $orderId : null;
            }
        }

        if (preg_match('/^ORD-(\d+)-/', $batch, $matches)) {
            return (int) $matches[1];
        }

        return $this->parseOrderIdFromDescription((string) ($entry->description ?? ''));
    }

    private function parseOrderIdFromDescription(string $description): ?int
    {
        if (preg_match('/طلب\s*(?:رقم\s*)?(\d+)/u', $description, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseOrderIdFromOrdOpsBatch(string $batch): ?int
    {
        $suffix = substr($batch, strlen(OrderPaymentSourceLedgerService::BATCH_PREFIX));
        $orderId = (int) strtok($suffix, '-');

        return $orderId > 0 ? $orderId : null;
    }

    private function extractExpenseNumber(string $text): ?string
    {
        if (preg_match('/(EX\d+)/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isPrepaidOrderEntry(AccountEntry $entry): bool
    {
        $batch = (string) ($entry->entry_batch_code ?? '');
        foreach (['ORD-PREPAID-PENDING-', 'ORD-PREPAID-', 'PARTCOLLECT-', 'PREPAID-REV-'] as $prefix) {
            if (str_starts_with($batch, $prefix)) {
                return true;
            }
        }

        $description = (string) ($entry->description ?? '');

        return str_contains($description, 'دفعة مقدمة')
            || str_contains($description, 'تحت الحساب')
            || str_contains($description, 'تحصيل دفعة');
    }
}
