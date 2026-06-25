<?php

namespace App\Services\Orders;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Order;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Mirror-reverses GL entries for order rollback without deleting historical rows.
 */
final class OrderRollbackAccountingService
{
    public function __construct(
        private AccountingService $accountingService,
    ) {
    }

    /**
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    public function reverseByBatchPattern(
        Order $order,
        string $batchPattern,
        int $userId,
        string $reason,
    ): array {
        $entries = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where('entry_batch_code', 'like', $batchPattern)
            ->get();

        return $this->mirrorReverseAccountEntries($entries, $order->id, $userId, $reason, 'ROLLBACK');
    }

    /**
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    public function reverseDeliveryTransfer(Order $order, int $userId, string $reason): array
    {
        return $this->reverseByBatchPattern(
            $order,
            'DELIVERY-' . $order->id . '-%',
            $userId,
            $reason,
        );
    }

    /**
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    public function reverseCollectionGl(Order $order, int $userId, string $reason): array
    {
        return $this->reverseByBatchPattern(
            $order,
            'ORD-OPS-' . $order->id . '-%',
            $userId,
            $reason,
        );
    }

    /**
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    public function reverseCourierCost(Order $order, int $userId, string $reason): array
    {
        return $this->reverseByBatchPattern(
            $order,
            'SHIP-COST-' . $order->id . '-%',
            $userId,
            $reason,
        );
    }

    /**
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    public function reverseCogsEntries(Order $order, int $userId, string $reason): array
    {
        $entries = AccountEntry::query()
            ->where('order_id', $order->id)
            ->where(function ($q) use ($order) {
                $q->where('description', 'like', '%تكلفة البضاعة المباعة%طلب%' . $order->id . '%')
                    ->orWhere('description', 'like', '%COGS%order%' . $order->id . '%');
            })
            ->get();

        return $this->mirrorReverseAccountEntries($entries, $order->id, $userId, $reason, 'ROLLBACK-COGS');
    }

    /**
     * @param  Collection<int, AccountEntry>  $entries
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    private function mirrorReverseAccountEntries(
        Collection $entries,
        int $orderId,
        int $userId,
        string $reason,
        string $batchPrefix,
    ): array {
        if ($entries->isEmpty()) {
            return [];
        }

        $results = [];
        $grouped = $entries->groupBy('daily_entry_id');

        foreach ($grouped as $dailyEntryId => $group) {
            if (! $dailyEntryId) {
                $results = array_merge($results, $this->mirrorReverseUngroupedEntries($group, $orderId, $userId, $reason, $batchPrefix));

                continue;
            }

            $original = DailyEntry::query()->find((int) $dailyEntryId);
            if (! $original) {
                continue;
            }

            $reversal = $this->mirrorReverseDailyEntry($original, $orderId, $userId, $reason, $batchPrefix);
            if ($reversal) {
                $results[] = $reversal;
            }
        }

        return $results;
    }

    /**
     * @param  Collection<int, AccountEntry>  $entries
     * @return list<array{batch_code: string, daily_entry_id: int, description: string, lines: int}>
     */
    private function mirrorReverseUngroupedEntries(
        Collection $entries,
        int $orderId,
        int $userId,
        string $reason,
        string $batchPrefix,
    ): array {
        $batchCode = $batchPrefix . '-' . $orderId . '-' . now()->format('YmdHis');
        $desc = 'عكس — ' . $reason . ' — طلب رقم ' . $orderId;

        $reversal = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => $desc,
            'user_id' => $userId,
        ]);

        $affected = [];
        $lineCount = 0;

        foreach ($entries as $entry) {
            $dr = round((float) $entry->credit, 2);
            $cr = round((float) $entry->debit, 2);
            if ($dr <= 0 && $cr <= 0) {
                continue;
            }

            DailyEntryItem::create([
                'daily_entry_id' => $reversal->id,
                'account_id' => (int) $entry->tree_account_id,
                'debit' => $dr,
                'credit' => $cr,
                'notes' => $desc,
            ]);

            AccountEntry::create([
                'tree_account_id' => (int) $entry->tree_account_id,
                'debit' => $dr,
                'credit' => $cr,
                'description' => $desc,
                'order_id' => $orderId,
                'entry_batch_code' => $batchCode,
                'daily_entry_id' => $reversal->id,
            ]);

            $affected[(int) $entry->tree_account_id] = true;
            $lineCount++;
        }

        $this->rebuildBalances(array_keys($affected), $orderId);

        if ($lineCount === 0) {
            $reversal->delete();

            return [];
        }

        return [[
            'batch_code' => $batchCode,
            'daily_entry_id' => (int) $reversal->id,
            'description' => $desc,
            'lines' => $lineCount,
        ]];
    }

    /**
     * @return array{batch_code: string, daily_entry_id: int, description: string, lines: int}|null
     */
    private function mirrorReverseDailyEntry(
        DailyEntry $original,
        int $orderId,
        int $userId,
        string $reason,
        string $batchPrefix,
    ): ?array {
        $items = DailyEntryItem::query()->where('daily_entry_id', $original->id)->get();
        if ($items->isEmpty()) {
            return null;
        }

        $batchCode = $batchPrefix . '-' . $orderId . '-' . now()->format('YmdHis') . '-' . $original->id;
        $desc = 'عكس — ' . $reason . ' — ' . $original->description;

        $reversal = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => $desc,
            'user_id' => $userId,
        ]);

        $affected = [];
        foreach ($items as $line) {
            $dr = round((float) $line->credit, 2);
            $cr = round((float) $line->debit, 2);

            DailyEntryItem::create([
                'daily_entry_id' => $reversal->id,
                'account_id' => (int) $line->account_id,
                'debit' => $dr,
                'credit' => $cr,
                'notes' => $desc,
            ]);

            AccountEntry::create([
                'tree_account_id' => (int) $line->account_id,
                'debit' => $dr,
                'credit' => $cr,
                'description' => $desc,
                'order_id' => $orderId,
                'entry_batch_code' => $batchCode,
                'daily_entry_id' => $reversal->id,
            ]);

            $affected[(int) $line->account_id] = true;
        }

        $this->rebuildBalances(array_keys($affected), $orderId);

        return [
            'batch_code' => $batchCode,
            'daily_entry_id' => (int) $reversal->id,
            'description' => $desc,
            'lines' => $items->count(),
        ];
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function rebuildBalances(array $accountIds, int $orderId): void
    {
        foreach ($accountIds as $accountId) {
            try {
                $this->accountingService->updateAccountHierarchyBalances((int) $accountId);
            } catch (\Throwable $e) {
                Log::warning('OrderRollbackAccountingService: tree balance rebuild failed', [
                    'order_id' => $orderId,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
