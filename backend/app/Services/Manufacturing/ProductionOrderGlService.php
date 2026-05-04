<?php

namespace App\Services\Manufacturing;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\Log;

/**
 * GL for production orders: material absorption into WIP, then FG receipt from WIP.
 * When debit and credit would hit the same inventory account (e.g. WIP→WIP), lines are skipped.
 */
final class ProductionOrderGlService
{
    public function __construct(
        private AccountingService $accountingService,
    ) {
    }

    /**
     * @param  array<int, array{category_id:int, amount:float}>  $consumptionLines
     */
    public function postMaterialConsumption(int $productionOrderId, array $consumptionLines, ?int $userId): void
    {
        $wipPoolStock = ProductionWarehouseResolver::wipStock();
        $wipDebit = TreeAccount::resolveInventoryAccountForStock($wipPoolStock);
        if (! $wipDebit) {
            Log::warning('ProductionOrderGlService: skip consumption GL — no WIP account', ['production_order_id' => $productionOrderId]);

            return;
        }

        $creditBuckets = [];
        foreach ($consumptionLines as $line) {
            $acc = TreeAccount::resolveInventoryAccountForCategoryId((int) $line['category_id']);
            if (! $acc) {
                continue;
            }
            if ((int) $acc->id === (int) $wipDebit->id) {
                continue;
            }
            $key = (int) $acc->id;
            $creditBuckets[$key] = ($creditBuckets[$key] ?? 0) + (float) $line['amount'];
        }

        $creditTotal = round(array_sum($creditBuckets), 4);
        if ($creditTotal <= 0.00001) {
            return;
        }

        $daily = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'استهلاك مواد — أمر إنتاج #' . $productionOrderId,
            'user_id' => $userId,
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $daily->id,
            'account_id' => $wipDebit->id,
            'debit' => $creditTotal,
            'credit' => 0,
            'notes' => 'WIP absorption (materials)',
        ]);

        AccountEntry::create([
            'tree_account_id' => $wipDebit->id,
            'debit' => $creditTotal,
            'credit' => 0,
            'description' => $daily->description,
            'daily_entry_id' => $daily->id,
        ]);

        foreach ($creditBuckets as $accId => $amt) {
            $amt = round((float) $amt, 4);
            if ($amt <= 0.00001) {
                continue;
            }
            DailyEntryItem::create([
                'daily_entry_id' => $daily->id,
                'account_id' => $accId,
                'debit' => 0,
                'credit' => $amt,
                'notes' => 'نقص مخزون مكون',
            ]);
            AccountEntry::create([
                'tree_account_id' => $accId,
                'debit' => 0,
                'credit' => $amt,
                'description' => $daily->description,
                'daily_entry_id' => $daily->id,
            ]);
            $this->accountingService->updateAccountHierarchyBalances((int) $accId);
        }

        $this->accountingService->updateAccountHierarchyBalances($wipDebit->id);
    }

    public function postCompletionToFinishedGoods(
        int $productionOrderId,
        float $amount,
        ?Stock $finishedStock,
        ?int $userId,
    ): void {
        if ($amount <= 0.00001) {
            return;
        }

        $fgStock = $finishedStock ?? ProductionWarehouseResolver::finishedGoodsStock();
        $fg = TreeAccount::resolveInventoryAccountForStock($fgStock);
        $wip = TreeAccount::resolveInventoryAccountForStock(ProductionWarehouseResolver::wipStock());

        if (! $fg || ! $wip) {
            Log::warning('ProductionOrderGlService: skip completion GL — missing FG or WIP account', [
                'production_order_id' => $productionOrderId,
            ]);

            return;
        }

        if ((int) $fg->id === (int) $wip->id) {
            return;
        }

        $daily = DailyEntry::create([
            'date' => now(),
            'entry_number' => DailyEntry::getNextEntryNumber(),
            'description' => 'إتمام أمر إنتاج — إضافة منتج تام #' . $productionOrderId,
            'user_id' => $userId,
        ]);

        DailyEntryItem::create([
            'daily_entry_id' => $daily->id,
            'account_id' => $fg->id,
            'debit' => $amount,
            'credit' => 0,
            'notes' => 'إضافة منتج تام للمخزون',
        ]);
        DailyEntryItem::create([
            'daily_entry_id' => $daily->id,
            'account_id' => $wip->id,
            'debit' => 0,
            'credit' => $amount,
            'notes' => 'إخراج من تحت التشغيل',
        ]);

        AccountEntry::create([
            'tree_account_id' => $fg->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $daily->description,
            'daily_entry_id' => $daily->id,
        ]);
        AccountEntry::create([
            'tree_account_id' => $wip->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $daily->description,
            'daily_entry_id' => $daily->id,
        ]);

        $this->accountingService->updateAccountHierarchyBalances($fg->id);
        $this->accountingService->updateAccountHierarchyBalances($wip->id);
    }
}
