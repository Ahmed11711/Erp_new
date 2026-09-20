<?php

namespace App\Console\Commands;

use App\Models\TreeAccount;
use App\Services\Accounting\PaymentSourceReconciliationService;
use Illuminate\Console\Command;

class SetPaymentSourceBalanceCommand extends Command
{
    protected $signature = 'payment-sources:set-balance
        {--type= : safe|bank|service_account}
        {--id= : معرف الخزينة/البنك/الحساب الخدمي}
        {--amount= : الرصيد الفعلي المطلوب (الواقع)}
        {--date= : تاريخ القيد (مثال 2026-01-01) — يُفضّل بداية الفترة}
        {--counter-account-id= : حساب مقابل (رأس مال / جاري / تسويات)}
        {--counter-account-code= : أو كود الحساب المقابل بدل المعرف}
        {--reason= : وصف القيد}
        {--dry-run : عرض دون تنفيذ}
        {--list-counters : عرض حسابات مقابل مقترحة}';

    protected $description = 'ضبط الرصيد الفعلي لخزينة/بنك/حساب خدمي مع قيد يومي (افتتاحي/تسوية) ومزامنة GL';

    public function handle(PaymentSourceReconciliationService $service): int
    {
        if ($this->option('list-counters')) {
            $this->info('حسابات مقابل مقترحة (حقوق ملكية / خصوم):');
            foreach ($service->suggestCounterAccounts() as $acc) {
                $this->line("  [{$acc->id}] {$acc->code} — {$acc->name} ({$acc->type})");
            }

            return Command::SUCCESS;
        }

        $type = (string) $this->option('type');
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $amount = $this->option('amount') !== null ? (float) $this->option('amount') : null;
        $date = $this->option('date') ?: now()->startOfYear()->format('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($type, ['safe', 'bank', 'service_account'], true) || ! $id || $amount === null) {
            $this->error('مطلوب: --type=safe|bank|service_account --id= --amount=');
            $this->line('مثال: php artisan payment-sources:set-balance --type=safe --id=1 --amount=150000 --counter-account-code=3000');
            $this->line('لعرض حسابات مقابل: php artisan payment-sources:set-balance --list-counters');

            return Command::FAILURE;
        }

        $counter = $this->resolveCounterAccount();
        if (! $counter) {
            $this->error('حدّد --counter-account-id أو --counter-account-code (أو --list-counters)');

            return Command::FAILURE;
        }

        $rows = $service->collectRows($type, $id);
        $row = $rows[0] ?? null;
        if (! $row) {
            $this->error('المصدر غير موجود.');

            return Command::FAILURE;
        }

        $reason = $this->option('reason')
            ?: 'رصيد افتتاحي / تسوية رصيد فعلي — ' . $service->typeLabel($type) . ' «' . $row['name'] . '»';

        $this->info('=== ضبط الرصيد الفعلي ===');
        $this->table(
            ['', 'القيمة'],
            [
                ['النوع', $service->typeLabel($type)],
                ['المعرف', $row['id']],
                ['الاسم', $row['name']],
                ['كود GL', $row['tree_code']],
                ['رصيد تشغيلي حالي', number_format($row['operational'], 2)],
                ['GL حالي (من القيود)', number_format($row['gl'], 2)],
                ['الرصيد المطلوب (واقع)', number_format($amount, 2)],
                ['فرق GL المطلوب', number_format(round($amount - $row['gl'], 2), 2)],
                ['تاريخ القيد', $date],
                ['حساب مقابل', "{$counter->code} — {$counter->name}"],
            ]
        );

        if (abs(round($amount - $row['gl'], 2)) < 0.01 && abs(round($amount - $row['operational'], 2)) < 0.01) {
            $this->info('✅ الرصيد مطابق بالفعل — لا حاجة لقيد.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[dry-run] لم يُنفَّذ أي تعديل.');

            return Command::SUCCESS;
        }

        if (! $this->confirm('تأكيد تسجيل قيد التسوية/الافتتاح؟', true)) {
            $this->warn('تم الإلغاء.');

            return Command::SUCCESS;
        }

        try {
            $result = $service->setTargetBalance(
                $type,
                $id,
                $amount,
                $counter,
                $date,
                $reason,
                1
            );

            $this->newLine();
            $this->info('✅ تم بنجاح:');
            $this->line("  قيد يومي #{$result['daily_entry_id']}");
            $this->line('  GL: ' . number_format($result['previous_gl'], 2) . ' → ' . number_format($result['target_balance'], 2));
            $this->line('  تشغيلي: ' . number_format($result['previous_operational'], 2) . ' → ' . number_format($result['operational_after'], 2));
            $this->newLine();
            $this->line('تحقق: php artisan reconcile:payment-sources --type=' . $type . ' --id=' . $id);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('فشل: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function resolveCounterAccount(): ?TreeAccount
    {
        if ($this->option('counter-account-id')) {
            return TreeAccount::find((int) $this->option('counter-account-id'));
        }

        if ($this->option('counter-account-code')) {
            return TreeAccount::where('code', $this->option('counter-account-code'))->first();
        }

        return TreeAccount::query()
            ->where('type', 'equity')
            ->whereDoesntHave('children')
            ->where(function ($q) {
                $q->where('name', 'like', '%رأس المال%')
                    ->orWhere('name', 'like', '%جاري%');
            })
            ->orderBy('code')
            ->first();
    }
}
