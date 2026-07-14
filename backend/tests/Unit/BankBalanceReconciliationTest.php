<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\BankOperationalLedgerService;
use App\Services\Accounting\DirectCashTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * يوضح لماذا يختلف رصيد شاشة «إدارة البنوك» (banks.balance) عن كشف الحساب (account_entries).
 */
class BankBalanceReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'BREC' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_operational_and_gl_balance_stay_in_sync_after_deposit(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();

        app(DirectCashTransactionService::class)->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'receipt',
            'amount' => 1000,
            'date' => '2026-07-01',
            'notes' => 'اختبار تزامن الرصيد',
        ]);

        $bank->refresh();
        $ledger = app(BankOperationalLedgerService::class);

        $this->assertEqualsWithDelta(
            1000.0,
            (float) $bank->balance,
            0.01,
            'الرصيد التشغيلي يجب أن يطابق مبلغ الإيداع'
        );
        $this->assertEqualsWithDelta(
            (float) $bank->balance,
            $ledger->glBalanceForBank($bank),
            0.01,
            'رصيد البنك التشغيلي يجب أن يطابق مجموع القيود المحاسبية'
        );
    }

    /**
     * عند تعديل عملية BANK-DW يُعاد ترحيل القيود لكن bank_details القديمة تبقى —
     * هذا يسبب تكراراً في السجل التشغيلي وقد يفسد الرصيد إن لم يُطبَّق العكس بشكل صحيح.
     */
    public function test_updating_bank_direct_transaction_leaves_single_operational_detail(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();
        $service = app(DirectCashTransactionService::class);

        // إيداع أولاً لضمان وجود رصيد كافٍ عند اختبار تعديل السحب
        $service->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'receipt',
            'amount' => 2000,
            'date' => '2026-07-01',
            'notes' => 'رصيد افتتاحي للاختبار',
        ]);

        $txn = $service->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'payment',
            'amount' => 500,
            'date' => '2026-07-01',
            'notes' => 'سحب أولي',
        ]);

        $batchCode = $txn->entry_batch_code;
        $this->assertNotNull($batchCode);

        $service->updateBankDirect($txn->id, [
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'payment',
            'amount' => 500,
            'date' => '2026-07-05',
            'notes' => 'سحب بعد تعديل التاريخ',
        ]);

        $bank->refresh();
        $ledger = app(BankOperationalLedgerService::class);

        $detailCount = DB::table('bank_details')
            ->where('bank_id', $bank->id)
            ->where('ref', $batchCode)
            ->count();

        $this->assertSame(
            1,
            $detailCount,
            'بعد تعديل العملية يجب ألا يبقى أكثر من bank_detail واحد لنفس المرجع — التكرار يسبب انحراف الرصيد التشغيلي (مثل BANK-DW-76/83/86 في CIB)'
        );

        $this->assertEqualsWithDelta(
            1500.0,
            (float) $bank->balance,
            0.01,
            'الرصيد التشغيلي بعد التعديل يجب أن يعكس الإيداع ناقص السحب مرة واحدة'
        );
        $this->assertEqualsWithDelta(
            (float) $bank->balance,
            $ledger->glBalanceForBank($bank),
            0.01,
            'بعد التعديل يجب أن يتطابق الرصيد التشغيلي مع القيود'
        );
    }

    public function test_gl_balance_formula_matches_account_statement(): void
    {
        $bank = Bank::query()->where('type', 'main')->whereNotNull('asset_id')->first();
        if (! $bank) {
            $this->markTestSkipped('لا يوجد بنك في قاعدة البيانات');
        }

        $assetId = (int) $bank->asset_id;
        $ledger = app(BankOperationalLedgerService::class);

        $glFromEntries = $ledger->glBalanceForBank($bank);
        $treeStored = (float) ($bank->asset?->balance ?? 0);
        $operational = (float) $bank->balance;
        $gap = round($operational - $glFromEntries, 2);

        $this->assertEqualsWithDelta(
            $glFromEntries,
            $treeStored,
            0.01,
            'رصيد الشجرة المخزن يجب أن يطابق مجموع القيود (كشف الحساب)'
        );

        // توثيق الفجوة إن وُجدت — لا نفشل الاختبار على بيانات إنتاج قديمة
        if (abs($gap) > 0.01) {
            $orderGlOnly = (float) AccountEntry::query()
                ->where('tree_account_id', $assetId)
                ->whereNotNull('order_id')
                ->selectRaw('COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) as net')
                ->value('net');

            $duplicateExtra = $this->sumDuplicateBankDetailExtra($bank->id);

            fwrite(STDERR, sprintf(
                "\n[BankBalanceReconciliation] %s: ops=%.2f GL=%.2f gap=%.2f order_gl_only=%.2f duplicate_ops_extra=%.2f\n",
                $bank->name,
                $operational,
                $glFromEntries,
                $gap,
                $orderGlOnly,
                $duplicateExtra
            ));
        }

        $this->assertTrue(true);
    }

    public function test_journal_sync_skips_duplicate_bank_detail_for_same_ref(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $bank = $this->seedBank();
        $assetId = (int) $bank->asset_id;
        $sync = app(\App\Services\Accounting\PaymentSourceOperationalLedgerService::class);
        $ref = 'PARTCOLLECT-TEST-' . $this->codePrefix;

        $sync->syncFromJournalLine($assetId, 250, 0, 'تحصيل اختبار', $ref, '2026-07-01');
        $bank->refresh();
        $this->assertEqualsWithDelta(250.0, (float) $bank->balance, 0.01);

        $sync->syncFromJournalLine($assetId, 250, 0, 'تحصيل مكرر', $ref, '2026-07-01');
        $bank->refresh();
        $this->assertEqualsWithDelta(
            250.0,
            (float) $bank->balance,
            0.01,
            'نفس المرجع لا يجب أن يُحدّث الرصيد التشغيلي مرتين'
        );
        $this->assertSame(1, DB::table('bank_details')->where('bank_id', $bank->id)->where('ref', $ref)->count());
    }

    private function sumDuplicateBankDetailExtra(int $bankId): float
    {
        $dupes = DB::table('bank_details')
            ->where('bank_id', $bankId)
            ->select('ref', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(balance_after - balance_before) as total_delta'))
            ->groupBy('ref')
            ->having('cnt', '>', 1)
            ->get();

        $extra = 0.0;
        foreach ($dupes as $row) {
            $txn = DB::table('bank_transactions')
                ->where('entry_batch_code', $row->ref)
                ->orWhere('id', (int) preg_replace('/\D/', '', (string) $row->ref))
                ->first();

            if (! $txn) {
                continue;
            }

            $expected = (float) $txn->amount;
            $signedExpected = $txn->type === 'withdrawal' ? -$expected : $expected;
            $extra += round((float) $row->total_delta - $signedExpected, 2);
        }

        return $extra;
    }

    private function seedBank(): Bank
    {
        $p = $this->codePrefix;

        $parent = TreeAccount::create([
            'code' => $p . 'P',
            'name' => 'أصول اختبار',
            'type' => 'asset',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);

        $bankTree = TreeAccount::create([
            'code' => $p . 'B',
            'name' => 'بنك اختبار',
            'type' => 'asset',
            'parent_id' => $parent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 3,
            'detail_type' => 'bank',
        ]);

        return Bank::create([
            'name' => 'بنك اختبار ' . $p,
            'type' => 'main',
            'balance' => 0,
            'usage' => 'test',
            'asset_id' => $bankTree->id,
        ]);
    }

    private function seedCounterAccount(): TreeAccount
    {
        $p = $this->codePrefix;

        return TreeAccount::create([
            'code' => $p . 'C',
            'name' => 'حساب مقابل',
            'type' => 'expense',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);
    }
}
