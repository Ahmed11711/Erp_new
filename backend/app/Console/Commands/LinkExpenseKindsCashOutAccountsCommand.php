<?php

namespace App\Console\Commands;

use App\Models\ExpenseKind;
use App\Models\TreeAccount;
use Illuminate\Console\Command;

class LinkExpenseKindsCashOutAccountsCommand extends Command
{
    protected $signature = 'expense-kinds:link-cash-out-accounts {--force : إعادة الربط حتى للفئات المربوطة مسبقاً خارج «النقد الصادر»}';

    protected $description = 'ربط فئات المصروف بحسابات فرعية تحت حساب الأب «النقد الصادر»';

    public function handle(): int
    {
        $parent = TreeAccount::ensureCashOutExpenseParent();
        $this->info('حساب الأب: '.$parent->name.' ('.$parent->code.')');

        $force = (bool) $this->option('force');
        $linked = 0;
        $skipped = 0;

        foreach (ExpenseKind::orderBy('id')->get() as $kind) {
            if ($kind->tree_account_id && ! $force) {
                $acc = TreeAccount::find((int) $kind->tree_account_id);
                if ($acc && TreeAccount::isUnderCashOutExpenseParent($acc)) {
                    $skipped++;
                    continue;
                }
            }

            if ($kind->tree_account_id && $force) {
                $acc = TreeAccount::find((int) $kind->tree_account_id);
                if ($acc && TreeAccount::isUnderCashOutExpenseParent($acc)) {
                    $skipped++;
                    continue;
                }
                $kind->tree_account_id = null;
                $kind->saveQuietly();
            }

            $expenseType = trim((string) $kind->expense_type) !== ''
                ? $kind->expense_type
                : 'مصروف تشغيل';

            $account = TreeAccount::ensureExpenseKindLedgerAccount($kind, $expenseType);
            $this->line("  #{$kind->id} {$kind->expense_kind} → {$account->code} {$account->name}");
            $linked++;
        }

        $this->info("تم ربط {$linked} فئة، وتخطي {$skipped}.");

        return self::SUCCESS;
    }
}
