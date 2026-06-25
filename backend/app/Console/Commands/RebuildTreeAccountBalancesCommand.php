<?php

namespace App\Console\Commands;

use App\Models\TreeAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Console\Command;

class RebuildTreeAccountBalancesCommand extends Command
{
    protected $signature = 'accounting:rebuild-tree-balances
        {--verify : عرض عينة من الحسابات بعد الإعادة}';

    protected $description = 'إعادة بناء أرصدة شجرة الحسابات من القيود المحاسبية (account_entries)';

    public function handle(TreeAccountBalanceRebuildService $service): int
    {
        $this->info('=== إعادة بناء أرصدة شجرة الحسابات ===');

        $stats = $service->rebuildAll();

        $this->info("تم تحديث {$stats['leaves_updated']} حساب طرفي و {$stats['parents_updated']} حساب تجميعي.");

        if ($this->option('verify')) {
            $this->newLine();
            $this->line('--- عينة تحقق ---');
            foreach (['1000', '1000211', '1000221', '1000223', '100023', '1000233', '400011', '2000'] as $code) {
                $a = TreeAccount::where('code', $code)->first();
                if ($a) {
                    $this->line("{$a->code} | {$a->name} | balance={$a->balance}");
                }
            }
        }

        return self::SUCCESS;
    }
}
