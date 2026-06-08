<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BankOperationalLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileBankGlCommand extends Command
{
    protected $signature = 'accounting:reconcile-banks
        {--bank-id= : بنك محدد}
        {--fix : ترحيل قيد تصحيحي للفروقات}
        {--counter-account-id= : حساب مقابل لقيد التصحيح (مطلوب مع --fix)}';

    protected $description = 'مقارنة رصيد البنوك التشغيلي (banks.balance) مع الرصيد المحاسبي (account_entries)';

    public function handle(BankOperationalLedgerService $ledger): int
    {
        $bankId = $this->option('bank-id');
        $fix = (bool) $this->option('fix');
        $counterId = $this->option('counter-account-id');

        if ($fix && ! $counterId) {
            $this->error('يجب تحديد --counter-account-id عند استخدام --fix');

            return Command::FAILURE;
        }

        $query = Bank::query()->where('type', 'main')->with('asset');
        if ($bankId) {
            $query->where('id', (int) $bankId);
        }

        $banks = $query->orderBy('name')->get();
        if ($banks->isEmpty()) {
            $this->warn('لا توجد بنوك للمراجعة.');

            return Command::SUCCESS;
        }

        $this->info('=== مقارنة أرصدة البنوك مع المحاسبة ===');
        $this->newLine();

        $rows = [];
        $hasGap = false;

        foreach ($banks as $bank) {
            $operational = (float) $bank->balance;
            $glFromEntries = $ledger->glBalanceForBank($bank);
            $treeStored = $bank->asset_id ? (float) ($bank->asset->balance ?? 0) : null;
            $gap = round($operational - $glFromEntries, 2);

            $orphanTypes = DB::table('bank_details')
                ->where('bank_id', $bank->id)
                ->whereIn('type', ['ايداع', 'سحب', 'تعديل', 'تحويل'])
                ->count();

            if (abs($gap) > 0.01) {
                $hasGap = true;
            }

            $rows[] = [
                $bank->id,
                $bank->name,
                $bank->asset?->code ?? '—',
                number_format($operational, 2),
                number_format($glFromEntries, 2),
                $treeStored !== null ? number_format($treeStored, 2) : '—',
                number_format($gap, 2),
                $orphanTypes,
            ];
        }

        $this->table(
            ['#', 'البنك', 'كود GL', 'رصيد تشغيلي', 'رصيد من القيود', 'رصيد الشجرة', 'الفرق', 'حركات قديمة*'],
            $rows
        );

        $this->line('* حركات من أنواع: ايداع / سحب / تعديل / تحويل (قد تكون بدون قيد قبل الإصلاح)');

        if (! $hasGap) {
            $this->info('✅ لا توجد فروقات بين رصيد البنوك والقيود.');

            return Command::SUCCESS;
        }

        if (! $fix) {
            $this->newLine();
            $this->warn('⚠️ وُجدت فروقات. لتصحيح بنك محدد:');
            $this->line('  php artisan accounting:reconcile-banks --bank-id=ID --fix --counter-account-id=TREE_ID');

            return Command::FAILURE;
        }

        $targetBank = $banks->firstWhere('id', (int) $bankId);
        if (! $targetBank) {
            $this->error('حدّد --bank-id لبنك واحد عند استخدام --fix');

            return Command::FAILURE;
        }

        if (! TreeAccount::where('id', (int) $counterId)->exists()) {
            $this->error('الحساب المقابل غير موجود');

            return Command::FAILURE;
        }

        $gap = round((float) $targetBank->balance - $ledger->glBalanceForBank($targetBank), 2);
        if (abs($gap) < 0.01) {
            $this->info('لا يوجد فرق يستدعي تصحيحاً لهذا البنك.');

            return Command::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $ledger->postGlReconciliationOnly(
                $targetBank,
                $gap,
                (int) $counterId,
                'أمر reconcile-banks — ' . now()->format('Y-m-d H:i')
            );

            if ($targetBank->asset_id) {
                app(AccountingService::class)->updateAccountHierarchyBalances((int) $targetBank->asset_id);
                app(AccountingService::class)->updateAccountHierarchyBalances((int) $counterId);
            }

            DB::commit();
            $this->info("✅ تم ترحيل قيد تصحيحي محاسبي بمقدار {$gap} لبنك «{$targetBank->name}» (بدون تغيير الرصيد التشغيلي).");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('فشل التصحيح: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
