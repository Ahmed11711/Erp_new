<?php

namespace App\Console\Commands;

use App\Services\Accounting\DirectCashTransactionService;
use Illuminate\Console\Command;

/**
 * حركات السحب/الإيداع البنكي والخزينة كانت تُرحَّل في account_entries فقط
 * بدون رأس قيد يومي، لذلك لا تظهر في شاشة القيود اليومية ولا يظهر المستخدم
 * في دفتر اليومية. هذا الأمر يلفّ الحركات القديمة برأس DailyEntry دون تكرار المبالغ.
 *
 * php artisan accounting:backfill-direct-cash-journals [--dry-run]
 */
class BackfillDirectCashJournalHeadersCommand extends Command
{
    protected $signature = 'accounting:backfill-direct-cash-journals
        {--dry-run : عرض العدد دون تعديل}';

    protected $description = 'إنشاء رؤوس قيود يومية لحركات البنك/الخزينة (BANK-DW / SAFE-DW) التي بلا DailyEntry';

    public function handle(DirectCashTransactionService $service): int
    {
        if ($this->option('dry-run')) {
            $count = \App\Models\AccountEntry::query()
                ->whereNull('daily_entry_id')
                ->whereNotNull('entry_batch_code')
                ->where(function ($q) {
                    $q->where('entry_batch_code', 'like', DirectCashTransactionService::BANK_BATCH_PREFIX . '%')
                        ->orWhere('entry_batch_code', 'like', DirectCashTransactionService::SAFE_BATCH_PREFIX . '%');
                })
                ->distinct()
                ->count('entry_batch_code');

            $this->info("سيتم إنشاء {$count} رأس قيد يومي (بدون تعديل).");

            return self::SUCCESS;
        }

        $created = $service->backfillMissingJournalHeaders();
        $this->info("تم إنشاء {$created} رأس قيد يومي لحركات البنك/الخزينة.");

        return self::SUCCESS;
    }
}
