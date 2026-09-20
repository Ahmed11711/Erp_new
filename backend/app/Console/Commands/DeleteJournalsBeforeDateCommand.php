<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Services\Accounting\OperationalLinkedBalanceSyncService;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * حذف قيود اليومية ودفتر الأستاذ قبل تاريخ معيّن (افتراضي 2026-05-04).
 * لا يمس دفعة الاستيراد الافتتاحي في نفس اليوم أو بعده.
 *
 * php artisan accounting:delete-journals-before 2026-05-04 --dry-run
 * php artisan accounting:delete-journals-before 2026-05-04 --force
 */
class DeleteJournalsBeforeDateCommand extends Command
{
    protected $signature = 'accounting:delete-journals-before
        {date=2026-05-04 : حذف القيود ذات تاريخ اليومية قبل هذا اليوم}
        {--dry-run : عرض دون حذف}
        {--force : تنفيذ الحذف}';

    protected $description = 'حذف القيود المحاسبية السابقة لتاريخ محدد وإعادة بناء أرصدة الشجرة';

    public function handle(
        TreeAccountBalanceRebuildService $rebuild,
        OperationalLinkedBalanceSyncService $operationalSync
    ): int {
        $cutoff = $this->argument('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $cutoff)) {
            $this->error('صيغة التاريخ يجب أن تكون YYYY-MM-DD.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->option('force')) {
            $this->error('أضف --dry-run للمعاينة أو --force للتنفيذ.');

            return self::FAILURE;
        }

        $effectiveDate = 'DATE(COALESCE(de.date, v.date, account_entries.created_at))';
        $base = AccountEntry::query()
            ->select('account_entries.*')
            ->leftJoin('daily_entries as de', 'account_entries.daily_entry_id', '=', 'de.id')
            ->leftJoin('vouchers as v', 'account_entries.voucher_id', '=', 'v.id')
            ->whereRaw("{$effectiveDate} < ?", [$cutoff]);

        $entries = (clone $base)->get();
        if ($entries->isEmpty()) {
            $this->info("لا توجد قيود قبل {$cutoff}.");

            return self::SUCCESS;
        }

        $dailyIds = $entries->pluck('daily_entry_id')->filter()->unique()->values()->all();
        $accountIds = $entries->pluck('tree_account_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $debit = round((float) $entries->sum('debit'), 2);
        $credit = round((float) $entries->sum('credit'), 2);

        $this->table(
            ['قيود', 'يوميات', 'حسابات', 'مدين', 'دائن'],
            [[$entries->count(), count($dailyIds), count($accountIds), $debit, $credit]]
        );

        $journals = DailyEntry::query()->whereIn('id', $dailyIds)->orderBy('date')->get();
        foreach ($journals as $journal) {
            $this->line(sprintf(
                '%s  #%s  %s',
                $journal->date?->format('Y-m-d') ?? '—',
                $journal->entry_number,
                $journal->description
            ));
        }

        if ($dryRun) {
            $this->info("[dry-run] لن يُحذف شيء. قيد الاستيراد في {$cutoff} وبعده يبقى.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($entries, $dailyIds) {
            $entryIds = $entries->pluck('id')->all();
            AccountEntry::query()->whereIn('id', $entryIds)->delete();

            if ($dailyIds !== []) {
                DailyEntryItem::query()->whereIn('daily_entry_id', $dailyIds)->delete();
                DailyEntry::query()->whereIn('id', $dailyIds)->delete();
            }
        });

        $rebuild->rebuildAll();
        $operationalSync->syncFromGl(
            $accountIds,
            'مزامنة بعد حذف القيود السابقة لـ '.$cutoff
        );

        $this->info(sprintf(
            'حُذف %d قيداً في %d يومية قبل %s، وأُعيد بناء الأرصدة.',
            $entries->count(),
            count($dailyIds),
            $cutoff
        ));

        return self::SUCCESS;
    }
}
