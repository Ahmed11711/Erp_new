<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\InventoryAccountsTrueUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * نقل رصيد المخزون المُرحَّل خطأً على حساب جذر (افتراضياً "الأصول" كود 1000)
 * إلى حسابات المخزون الفرعية الصحيحة، عبر قيد إعادة تبويب واحد متوازن بلا أثر على الأرباح/الخسائر.
 *
 * الاستخدام:
 *   php artisan inventory:relocate-gl            (معاينة فقط)
 *   php artisan inventory:relocate-gl --apply    (ترحيل القيد فعلياً)
 */
class RelocateInventoryGlBalanceCommand extends Command
{
    protected $signature = 'inventory:relocate-gl
        {--source-code=1000 : كود الحساب الذي رُحِّل عليه المخزون خطأً (الطرف المقابل لإعادة التبويب)}
        {--apply : ترحيل القيد فعلياً بدل المعاينة}';

    protected $description = 'نقل رصيد المخزون المُرحَّل خطأً على الحساب الجذر إلى حسابات المخزون الفرعية الصحيحة';

    public function handle(InventoryAccountsTrueUpService $trueUp, AccountingService $accounting): int
    {
        $sourceCode = (string) $this->option('source-code');
        $source = TreeAccount::query()->where('code', $sourceCode)->first();
        if (! $source) {
            $this->error("لم يُعثر على الحساب المصدر بالكود {$sourceCode}.");

            return self::FAILURE;
        }

        $targets = $trueUp->computeTargetCostByInventoryAccount();

        $lines = [];
        $net = 0.0;
        $this->info('=== الفروع وأهدافها (أساس التكلفة) ===');
        foreach ($targets as $accId => $target) {
            $accId = (int) $accId;
            if ($accId === (int) $source->id) {
                continue; // الحساب المصدر يُعالَج كطرف مقابل لاحقاً
            }
            $acc = TreeAccount::find($accId);
            if (! $acc) {
                continue;
            }
            $book = $trueUp->bookBalanceFromEntries($accId);
            $delta = round((float) $target - $book, 2);
            $this->line(sprintf(
                '  %s (%s): هدف=%s، رصيد حالي=%s، فرق=%s',
                $acc->code,
                $acc->name,
                number_format((float) $target, 2),
                number_format($book, 2),
                number_format($delta, 2)
            ));
            if (abs($delta) < 0.02) {
                continue;
            }
            $lines[] = [
                'id' => $accId,
                'debit' => $delta > 0 ? $delta : 0.0,
                'credit' => $delta < 0 ? abs($delta) : 0.0,
                'note' => 'نقل رصيد مخزون إلى الحساب الفرعي الصحيح',
            ];
            $net += $delta;
        }

        if (count($lines) === 0) {
            $this->info('لا توجد فروقات تتطلب نقلاً. الفروع متطابقة بالفعل مع تكلفة الأصناف.');

            return self::SUCCESS;
        }

        $net = round($net, 2);
        if (abs($net) >= 0.02) {
            $lines[] = [
                'id' => (int) $source->id,
                'debit' => $net < 0 ? abs($net) : 0.0,
                'credit' => $net > 0 ? $net : 0.0,
                'note' => 'تخفيض رصيد مخزون كان مُرحَّلاً خطأً على هذا الحساب',
            ];
        }

        $sourceBook = $trueUp->bookBalanceFromEntries((int) $source->id);
        $this->newLine();
        $this->info(sprintf(
            'الطرف المقابل: %s (%s) — رصيده الحالي=%s، سيتغير بمقدار %s',
            $source->code,
            $source->name,
            number_format($sourceBook, 2),
            number_format(-$net, 2)
        ));

        $sumDr = array_sum(array_column($lines, 'debit'));
        $sumCr = array_sum(array_column($lines, 'credit'));
        $this->newLine();
        $this->info('=== بنود القيد ===');
        foreach ($lines as $ln) {
            $acc = TreeAccount::find($ln['id']);
            $this->line(sprintf(
                '  %s (%s): مدين=%s، دائن=%s',
                $acc->code ?? $ln['id'],
                $acc->name ?? '',
                number_format($ln['debit'], 2),
                number_format($ln['credit'], 2)
            ));
        }
        $this->line('  ----');
        $this->line(sprintf('  إجمالي مدين=%s، إجمالي دائن=%s', number_format($sumDr, 2), number_format($sumCr, 2)));

        if (abs($sumDr - $sumCr) > 0.05) {
            $this->error('القيد غير متوازن — تم الإيقاف.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('وضع المعاينة فقط. أضف --apply لترحيل القيد فعلياً.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($lines, $accounting) {
            $entryNumber = DailyEntry::getNextEntryNumber();
            $desc = 'إعادة تبويب مخزون — نقل من الحساب الجذر إلى الحسابات الفرعية (' . now()->format('Y-m-d H:i') . ')';
            $dailyEntry = DailyEntry::create([
                'date' => now(),
                'entry_number' => $entryNumber,
                'description' => $desc,
                'user_id' => null,
            ]);

            foreach ($lines as $ln) {
                DailyEntryItem::create([
                    'daily_entry_id' => $dailyEntry->id,
                    'account_id' => $ln['id'],
                    'debit' => $ln['debit'],
                    'credit' => $ln['credit'],
                    'notes' => $ln['note'],
                ]);
                AccountEntry::create([
                    'tree_account_id' => $ln['id'],
                    'debit' => $ln['debit'],
                    'credit' => $ln['credit'],
                    'description' => $desc . ' — ' . $ln['note'],
                    'daily_entry_id' => $dailyEntry->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($lines as $ln) {
                $accounting->updateAccountHierarchyBalances((int) $ln['id']);
            }

            $this->newLine();
            $this->info('تم ترحيل قيد إعادة التبويب رقم: ' . $dailyEntry->entry_number . ' (معرّف ' . $dailyEntry->id . ').');
        });

        return self::SUCCESS;
    }
}
