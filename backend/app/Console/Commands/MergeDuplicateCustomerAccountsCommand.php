<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\TreeAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateCustomerAccountsCommand extends Command
{
    protected $signature = 'accounting:merge-duplicate-customers {--dry-run : Show what would be merged without making changes}';
    protected $description = 'Find and merge duplicate customer accounts (name-only vs name+phone)';

    public function handle(TreeAccountBalanceRebuildService $balanceRebuild): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '=== DRY RUN — no changes will be made ===' : '=== Merging duplicate customer accounts ===');
        $this->newLine();

        $nameOnlyAccounts = TreeAccount::where('level', 4)
            ->where('type', 'asset')
            ->where('name', 'NOT LIKE', '% - %')
            ->get();

        $mergeCount = 0;

        foreach ($nameOnlyAccounts as $nameOnly) {
            $withPhone = TreeAccount::where('level', 4)
                ->where('type', 'asset')
                ->where('name', 'LIKE', $nameOnly->name . ' - %')
                ->where('id', '!=', $nameOnly->id)
                ->first();

            if (!$withPhone) {
                continue;
            }

            $nameOnlyEntries = AccountEntry::where('tree_account_id', $nameOnly->id)->count();
            $withPhoneEntries = AccountEntry::where('tree_account_id', $withPhone->id)->count();

            $nameOnlyBalance = AccountEntry::where('tree_account_id', $nameOnly->id)
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();
            $withPhoneBalance = AccountEntry::where('tree_account_id', $withPhone->id)
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $this->warn("DUPLICATE FOUND:");
            $this->line("  Name-only:  [{$nameOnly->code}] \"{$nameOnly->name}\" — {$nameOnlyEntries} entries, balance=" . ($nameOnlyBalance->d - $nameOnlyBalance->c));
            $this->line("  With-phone: [{$withPhone->code}] \"{$withPhone->name}\" — {$withPhoneEntries} entries, balance=" . ($withPhoneBalance->d - $withPhoneBalance->c));

            if (!$dryRun) {
                DB::transaction(function () use ($nameOnly, $withPhone) {
                    AccountEntry::where('tree_account_id', $nameOnly->id)
                        ->update(['tree_account_id' => $withPhone->id]);

                    DB::table('daily_entry_items')
                        ->where('account_id', $nameOnly->id)
                        ->update(['account_id' => $withPhone->id]);

                    $balanceRebuild->applyBalanceFromEntries($withPhone->fresh());

                    $nameOnly->delete();
                });

                $newBalance = AccountEntry::where('tree_account_id', $withPhone->id)
                    ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as bal')
                    ->value('bal');

                $this->info("  → MERGED into \"{$withPhone->name}\", new balance = {$newBalance}");
            } else {
                $mergedBalance = ($nameOnlyBalance->d + $withPhoneBalance->d) - ($nameOnlyBalance->c + $withPhoneBalance->c);
                $this->info("  → WOULD MERGE into \"{$withPhone->name}\", expected balance = {$mergedBalance}");
            }

            $mergeCount++;
            $this->newLine();
        }

        if ($mergeCount === 0) {
            $this->info('No duplicate customer accounts found.');
            return Command::SUCCESS;
        }

        if (!$dryRun) {
            $this->info('إعادة تجميع أرصدة الشجرة...');
            $balanceRebuild->rebuildAll();
            $this->info("Done! Merged {$mergeCount} duplicate account(s).");
        } else {
            $this->info("Found {$mergeCount} duplicate(s). Run without --dry-run to merge.");
        }

        return Command::SUCCESS;
    }

}
