<?php

namespace Database\Seeders;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * يُنشئ حركات قيد مزدوجة تجريبية (صرف) على أول خزنة وبنك وحساب خدمي متوفرين،
 * مشابهة لمنطق صرف الخزينة في SafeController، لعرضها في «كشف حساب تفصيلي».
 * آمن للتشغيل مرة واحدة (يتحقق من وصف يحتوي على العلامة أدناه).
 */
class DemoSimilarFinancialMovementsSeeder extends Seeder
{
    private const TAG = '[DEMO_STMT_2026]';

    public function run(): void
    {
        if (AccountEntry::where('description', 'like', '%' . self::TAG . '%')->exists()) {
            $this->command?->info('تجاوز: حركات التجربة موجودة مسبقاً (' . self::TAG . ').');

            return;
        }

        $counter = TreeAccount::where('type', 'expense')
            ->whereDoesntHave('children')
            ->orderBy('id')
            ->first();

        if (!$counter) {
            $this->command?->warn('لا يوجد حساب مصروف طرفي لاستخدامه كحساب مقابل.');

            return;
        }

        /** @var AccountingService $acc */
        $acc = app(AccountingService::class);
        $now = now();
        $amount = 150.00;

        DB::transaction(function () use ($counter, $acc, $now, $amount) {
            $safe = Safe::whereNotNull('account_id')->first();
            if ($safe) {
                $this->postSafeStylePayment($safe->account_id, $counter->id, $amount, self::TAG . ' صرف تجريبي من خزينة — ' . $safe->name, $now, $acc);
                $safe->decrement('balance', $amount);
            }

            $bank = Bank::whereNotNull('asset_id')->first();
            if ($bank) {
                $this->postSafeStylePayment($bank->asset_id, $counter->id, $amount, self::TAG . ' صرف تجريبي من بنك — ' . $bank->name, $now, $acc);
                $bank->decrement('balance', $amount);
            }

            $svc = ServiceAccount::whereNotNull('account_id')->first();
            if ($svc) {
                $this->postSafeStylePayment($svc->account_id, $counter->id, $amount, self::TAG . ' صرف تجريبي من حساب خدمي — ' . $svc->name, $now, $acc);
                $svc->decrement('balance', $amount);
            }
        });

        $this->command?->info('تم إنشاء حركات التجربة (إن وُجدت خزنة/بنك/حساب خدمي).');
    }

    /**
     * نفس منطق صرف الخزينة: دائن على الحساب النقدي/الخدمي، مدين على حساب مقابل.
     */
    private function postSafeStylePayment(
        int $creditTreeId,
        int $debitTreeId,
        float $amount,
        string $desc,
        $date,
        AccountingService $acc
    ): void {
        AccountEntry::create([
            'tree_account_id' => $creditTreeId,
            'debit' => 0,
            'credit' => $amount,
            'description' => $desc,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        AccountEntry::create([
            'tree_account_id' => $debitTreeId,
            'debit' => $amount,
            'credit' => 0,
            'description' => $desc,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $acc->updateAccountHierarchyBalances($creditTreeId);
        $acc->updateAccountHierarchyBalances($debitTreeId);
    }
}
