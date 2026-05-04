<?php

namespace App\Console\Commands;

use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\TreeAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * تصفير قيود اليومية وسجلات دفتر الأستاذ وأرصدة الشجرة — للاختبار المحلي فقط.
 */
class ResetGlAndJournalsForTestingCommand extends Command
{
    protected $signature = 'erp:reset-gl-testing {--force : تأكيد التنفيذ} {--yes : تخطي سؤال التأكيد (تلقائي من أوامر أخرى)}';

    protected $description = 'حذف القيود اليومية وحسابات الدفتر وصفّر أرصدة شجرة الحسابات (اختبار؛ لا يُستخدم على إنتاج)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('أضف --force لتنفيذ عملية خطيرة تُمسح القيود المحاسبية.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('سيتم حذف كل القيود اليومية وحسابات الدفتر وتصفير أرصدة الشجرة. متابعة؟')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            AccountEntry::query()->delete();
            DailyEntryItem::query()->delete();
            DailyEntry::query()->delete();

            TreeAccount::query()->update([
                'balance' => 0,
                'debit_balance' => 0,
                'credit_balance' => 0,
                'updated_at' => now(),
            ]);
        });

        $this->info('تم حذف القيود اليومية وحسابات الدفتر وتصفير أرصدة tree_accounts.');
        $this->warn('لم يُمس المخزون أو فواتير المشتريات؛ لإعادة اختبار متكامل صفّر مخزون الأصناف يدوياً أو استخدم أدوات منفصلة.');

        return self::SUCCESS;
    }
}
