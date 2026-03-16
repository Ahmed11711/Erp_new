<?php

namespace App\Console\Commands;

use App\Services\Accounting\AccountingService;
use Illuminate\Console\Command;

class RecalculateAccountBalancesCommand extends Command
{
    protected $signature = 'accounting:recalculate-balances';
    protected $description = 'إعادة حساب جميع أرصدة شجرة الحسابات من الصفر';

    public function handle(AccountingService $accountingService): int
    {
        $this->info('جاري إعادة حساب أرصدة الشجرة...');

        $result = $accountingService->recalculateAllHierarchyBalances();

        if ($result['success']) {
            $this->info($result['message']);
            $this->info("تم تحديث {$result['updated_count']} حساب طرفي");
            return Command::SUCCESS;
        }

        $this->error($result['message']);
        foreach ($result['errors'] ?? [] as $err) {
            $this->error("  - {$err}");
        }
        return Command::FAILURE;
    }
}
