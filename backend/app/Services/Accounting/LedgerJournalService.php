<?php

namespace App\Services\Accounting;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central double-entry posting: one DailyEntry header, mirrored DailyEntryItem rows,
 * and AccountEntry lines. Validates debits = credits before persisting.
 *
 * The logical "journal" in this ERP is a {@see DailyEntry} plus its {@see AccountEntry} lines.
 */
class LedgerJournalService
{
    public function __construct(
        private AccountingService $accountingService
    ) {
    }

    /**
     * @param  array<int, array{account_id: int|null, debit: float|int, credit: float|int, description: string}>  $lines
     */
    public function postBalancedJournal(
        array $lines,
        string $headerDescription,
        ?int $orderId = null,
        ?string $entryBatchCode = null,
        ?int $userId = null,
        ?\DateTimeInterface $date = null
    ): DailyEntry {
        $normalized = [];
        foreach ($lines as $line) {
            $dr = round((float) ($line['debit'] ?? 0), 2);
            $cr = round((float) ($line['credit'] ?? 0), 2);
            if ($dr < 0 || $cr < 0) {
                throw new \InvalidArgumentException('Journal lines cannot have negative debit/credit amounts.');
            }
            if ($dr > 0 && $cr > 0) {
                throw new \InvalidArgumentException('A single journal line cannot be both debit and credit.');
            }
            if ($dr == 0.0 && $cr == 0.0) {
                continue;
            }
            $aid = isset($line['account_id']) ? (int) $line['account_id'] : null;
            if (!$aid) {
                throw new \InvalidArgumentException('Journal line missing account_id.');
            }
            $normalized[] = [
                'account_id' => $aid,
                'debit' => $dr,
                'credit' => $cr,
                'description' => (string) ($line['description'] ?? $headerDescription),
            ];
        }

        $sumDr = round(array_sum(array_column($normalized, 'debit')), 2);
        $sumCr = round(array_sum(array_column($normalized, 'credit')), 2);
        if (empty($normalized)) {
            throw new \InvalidArgumentException('Journal has no lines.');
        }
        if (abs($sumDr - $sumCr) > 0.009) {
            throw new \InvalidArgumentException(
                "Journal not balanced: total debit {$sumDr} != total credit {$sumCr} ({$headerDescription})"
            );
        }

        return DB::transaction(function () use ($normalized, $headerDescription, $orderId, $entryBatchCode, $userId, $date) {
            $dailyEntry = DailyEntry::create([
                'date' => $date ?? now(),
                'entry_number' => DailyEntry::getNextEntryNumber(),
                'description' => $headerDescription,
                'user_id' => $userId ?? auth()->id() ?? 1,
            ]);

            $touched = [];
            foreach ($normalized as $line) {
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'notes' => $line['description'],
                ]);

                AccountEntry::create([
                    'tree_account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'],
                    'order_id' => $orderId,
                    'entry_batch_code' => $entryBatchCode,
                    'daily_entry_id' => $dailyEntry->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $touched[$line['account_id']] = true;
            }

            foreach (array_keys($touched) as $accountId) {
                try {
                    $this->accountingService->updateAccountHierarchyBalances((int) $accountId);
                } catch (\Throwable $e) {
                    Log::warning('LedgerJournalService: hierarchy update failed', [
                        'account_id' => $accountId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $dailyEntry;
        });
    }

    /** Dr Cash/Bank/Safe asset, Cr Customer AR — reduces what the customer owes. */
    public function postCustomerCollection(
        int $customerAccountId,
        int $cashTreeAccountId,
        float $amount,
        string $description,
        ?int $orderId,
        string $batchCode
    ): DailyEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Collection amount must be positive.');
        }

        return $this->postBalancedJournal(
            [
                [
                    'account_id' => $cashTreeAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description . ' — تحصيل نقدي',
                ],
                [
                    'account_id' => $customerAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description . ' — تخفيض ذمة العميل',
                ],
            ],
            $description,
            $orderId,
            $batchCode
        );
    }

    /**
     * Dr Customer AR, Cr Cash — reverse of a collection (e.g. prepaid reduced on order edit, or refund).
     * Increases customer balance / restores prepayment credit side.
     */
    public function postCustomerCollectionReversal(
        int $customerAccountId,
        int $cashTreeAccountId,
        float $amount,
        string $description,
        ?int $orderId,
        string $batchCode
    ): DailyEntry {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Reversal amount must be positive.');
        }

        return $this->postBalancedJournal(
            [
                [
                    'account_id' => $customerAccountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => $description . ' — إعادة ذمة العميل',
                ],
                [
                    'account_id' => $cashTreeAccountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => $description . ' — إرجاع نقدية',
                ],
            ],
            $description,
            $orderId,
            $batchCode
        );
    }
}
