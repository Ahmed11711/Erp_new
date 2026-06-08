<?php

namespace App\Console\Commands;

use App\Models\TreeAccount;
use App\Services\Accounting\PaymentSourceReconciliationService;
use Illuminate\Console\Command;

class ReconcilePaymentSourcesCommand extends Command
{
    protected $signature = 'reconcile:payment-sources
        {--type=all : all|safe|bank|service_account}
        {--id= : معرف مصدر محدد (safe_id أو bank_id أو service_account_id)}
        {--fix-operational : ضبط الرصيد التشغيلي ليطابق GL}
        {--fix-gl : ترحيل قيد تصحيحي GL ليطابق الرصيد التشغيلي}
        {--counter-account-id= : حساب مقابل مطلوب مع --fix-gl}
        {--dry-run : عرض التصحيحات دون تنفيذ}';

    protected $description = 'مطابقة أرصدة الخزائن والبنوك والحسابات الخدمية مع GL (account_entries)';

    public function handle(PaymentSourceReconciliationService $service): int
    {
        $type = (string) $this->option('type');
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $fixOperational = (bool) $this->option('fix-operational');
        $fixGl = (bool) $this->option('fix-gl');
        $dryRun = (bool) $this->option('dry-run');
        $counterId = $this->option('counter-account-id');

        if (! in_array($type, ['all', 'safe', 'bank', 'service_account'], true)) {
            $this->error('قيمة --type غير صحيحة. استخدم: all, safe, bank, service_account');

            return Command::FAILURE;
        }

        if ($fixOperational && $fixGl) {
            $this->error('استخدم --fix-operational أو --fix-gl وليس الاثنين معاً.');

            return Command::FAILURE;
        }

        if ($fixGl && ! $counterId) {
            $this->error('يجب تحديد --counter-account-id عند استخدام --fix-gl');

            return Command::FAILURE;
        }

        if (($fixOperational || $fixGl) && $type === 'all' && ! $id) {
            $this->error('عند التصحيح حدّد --type و --id لمصدر واحد، أو نفّذ التقرير فقط بدون --fix.');

            return Command::FAILURE;
        }

        if ($fixGl && $counterId && ! TreeAccount::where('id', (int) $counterId)->exists()) {
            $this->error('الحساب المقابل غير موجود في شجرة الحسابات.');

            return Command::FAILURE;
        }

        $rows = $service->collectRows($type === 'all' ? null : $type, $id);

        if ($rows === []) {
            $this->warn('لا توجد مصادر نقد مطابقة للفلتر.');

            return Command::SUCCESS;
        }

        $this->info('=== مطابقة مصادر النقد (تشغيلي ↔ GL) ===');
        $this->newLine();

        $tableRows = [];
        $gapCount = 0;
        $treeMismatchCount = 0;

        foreach ($rows as $row) {
            if (abs($row['gap']) > 0.01) {
                $gapCount++;
            }
            if ($row['tree_stored'] !== null && abs($row['tree_stored'] - $row['gl']) > 0.01) {
                $treeMismatchCount++;
            }

            $tableRows[] = [
                $service->typeLabel($row['type']),
                $row['id'],
                $row['name'],
                $row['tree_code'],
                number_format($row['operational'], 2),
                number_format($row['gl'], 2),
                $row['tree_stored'] !== null ? number_format($row['tree_stored'], 2) : '—',
                number_format($row['gap'], 2),
            ];
        }

        $this->table(
            ['النوع', '#', 'الاسم', 'كود GL', 'رصيد تشغيلي', 'GL من القيود', 'رصيد الشجرة', 'الفرق (تشغيلي−GL)'],
            $tableRows
        );

        $this->newLine();
        $this->line("مصادر بفروقات تشغيلي/GL: {$gapCount} / " . count($rows));
        if ($treeMismatchCount > 0) {
            $this->warn("⚠️ {$treeMismatchCount} مصدر: رصيد الشجرة المخزّن ≠ مجموع القيود — شغّل accounting:refresh-balances إن وُجد.");
        }

        if ($gapCount === 0) {
            $this->info('✅ لا توجد فروقات بين الأرصدة التشغيلية و GL.');

            return Command::SUCCESS;
        }

        if (! $fixOperational && ! $fixGl) {
            $this->newLine();
            $this->warn('⚠️ وُجدت فروقات. أمثلة تصحيح:');
            $this->line('  php artisan reconcile:payment-sources --type=safe --id=1 --fix-operational');
            $this->line('  php artisan reconcile:payment-sources --type=bank --id=2 --fix-gl --counter-account-id=100');

            return Command::FAILURE;
        }

        $targetType = $type === 'all' ? $rows[0]['type'] : $type;
        $targetId = $id ?? $rows[0]['id'];
        $targetRow = $service->collectRows($targetType, $targetId)[0] ?? null;

        if (! $targetRow) {
            $this->error('تعذر تحديد المصدر للتصحيح.');

            return Command::FAILURE;
        }

        if (abs($targetRow['gap']) < 0.01) {
            $this->info('لا يوجد فرق يستدعي تصحيحاً لهذا المصدر.');

            return Command::SUCCESS;
        }

        $label = $service->typeLabel($targetRow['type']) . ' «' . $targetRow['name'] . '»';
        $reason = 'reconcile:payment-sources ' . now()->format('Y-m-d H:i');

        if ($dryRun) {
            $this->warn("[dry-run] {$label}: فرق " . number_format($targetRow['gap'], 2));
            if ($fixOperational) {
                $this->line('  → سيُضبط الرصيد التشغيلي من ' . number_format($targetRow['operational'], 2) . ' إلى ' . number_format($targetRow['gl'], 2));
            } else {
                $this->line('  → سيُرحَّل قيد GL بمقدار ' . number_format(abs($targetRow['gap']), 2) . ' (حساب مقابل #' . $counterId . ')');
            }

            return Command::SUCCESS;
        }

        try {
            if ($fixOperational) {
                $result = $service->syncOperationalToGl($targetRow['type'], $targetRow['id'], $reason);
                $this->info("✅ {$label}: الرصيد التشغيلي {$result['before']} → {$result['after']} (GL: {$result['gl']})");
            } else {
                $service->syncGlToOperational($targetRow['type'], $targetRow['id'], (int) $counterId, $reason);
                $this->info("✅ {$label}: تم ترحيل قيد GL تصحيحي بمقدار " . number_format($targetRow['gap'], 2) . ' (الرصيد التشغيلي دون تغيير).');
            }
        } catch (\Throwable $e) {
            $this->error('فشل التصحيح: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
