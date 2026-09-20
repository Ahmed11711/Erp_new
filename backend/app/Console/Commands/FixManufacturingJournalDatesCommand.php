<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\ConfirmedManfucture;
use App\Models\DailyEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * قيود أوامر التصنيع كانت تُحفظ بتاريخ now() وليس تاريخ الأمر،
 * لذلك لا تظهر في القيود/دفتر اليومية عند فلترة يوم التصنيع.
 *
 * php artisan accounting:fix-manufacturing-journal-dates [--dry-run]
 */
class FixManufacturingJournalDatesCommand extends Command
{
    protected $signature = 'accounting:fix-manufacturing-journal-dates
        {--dry-run : عرض دون تعديل}';

    protected $description = 'ضبط تاريخ قيود أوامر التصنيع ليطابق تاريخ الأمر وليس تاريخ الترحيل';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        ConfirmedManfucture::query()->orderBy('id')->each(function (ConfirmedManfucture $order) use ($dryRun, &$updated) {
            $orderDate = Carbon::parse($order->date)->toDateString();
            $descriptions = [
                'استهلاك مواد خام — أمر تصنيع #' . $order->id,
                'إتمام تصنيع — أمر #' . $order->id,
            ];

            $entries = DailyEntry::query()
                ->whereIn('description', $descriptions)
                ->get();

            foreach ($entries as $entry) {
                $current = optional($entry->date)->format('Y-m-d');
                if ($current === $orderDate) {
                    continue;
                }

                $updated++;
                if ($dryRun) {
                    $this->line("قيد {$entry->entry_number}: {$current} → {$orderDate} ({$entry->description})");
                    continue;
                }

                $entry->date = $orderDate;
                $entry->save();

                $postedAt = Carbon::parse($orderDate)->startOfDay();
                AccountEntry::query()
                    ->where('daily_entry_id', $entry->id)
                    ->update([
                        'created_at' => $postedAt,
                        'updated_at' => $postedAt,
                    ]);
            }
        });

        $this->info($dryRun
            ? "سيتم تعديل {$updated} قيد تصنيع."
            : "تم ضبط تاريخ {$updated} قيد تصنيع.");

        return self::SUCCESS;
    }
}
