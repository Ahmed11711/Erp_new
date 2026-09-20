<?php

namespace App\Console\Commands;

use App\Services\Expenses\ExpensePurgeService;
use Illuminate\Console\Command;

class PurgeExpensesCommand extends Command
{
    protected $signature = 'erp:purge-expenses
                            {--force : تأكيد التنفيذ (إلزامي)}
                            {--yes : تخطي سؤال التأكيد}';

    protected $description = 'حذف جميع المصروفات وقيودها المحاسبية (اختبار/إعادة ضبط فقط)';

    public function handle(ExpensePurgeService $purgeService): int
    {
        if (! $this->option('force')) {
            $this->error('أضف --force لتنفيذ عملية خطيرة تمس جميع المصروفات وقيودها.');

            return self::FAILURE;
        }

        $preview = $purgeService->preview();

        $this->warn('=== حذف جميع المصروفات ===');
        $this->line("  مصروفات نشطة: {$preview['active_expense_count']}");
        $this->line("  إجمالي السجلات: {$preview['total_expense_count']}");
        $this->line("  قيود محاسبية (EXP-*): {$preview['gl_entry_count']}");
        $this->newLine();
        $this->warn('سيتم استرداد أرصدة الخزن/البنوك/الحسابات الخدمية للمصروفات النشطة.');
        $this->warn('سيتم إعادة بناء أرصدة شجرة الحسابات من القيود المتبقية.');

        if ($preview['total_expense_count'] === 0) {
            $this->info('لا توجد مصروفات للحذف.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('هل تريد المتابعة؟')) {
            return self::SUCCESS;
        }

        $counts = $purgeService->purge();

        $this->newLine();
        $this->info('=== تم الحذف ===');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        return self::SUCCESS;
    }
}
