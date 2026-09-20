<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\Bank;
use App\Models\DailyEntry;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\DirectCashTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DirectCashJournalHeaderTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'DJH' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_bank_deposit_creates_daily_entry_with_user_and_date(): void
    {
        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();

        $txn = app(DirectCashTransactionService::class)->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'receipt',
            'amount' => 99047,
            'date' => '2026-05-05',
            'notes' => 'سداد اوراق',
        ]);

        $glLines = AccountEntry::query()
            ->where('entry_batch_code', $txn->entry_batch_code)
            ->get();

        $this->assertCount(2, $glLines);
        $this->assertTrue($glLines->every(fn (AccountEntry $e) => $e->daily_entry_id !== null));

        $daily = DailyEntry::query()->find($glLines->first()->daily_entry_id);
        $this->assertNotNull($daily);
        $this->assertSame((int) $user->id, (int) $daily->user_id);
        $this->assertSame('2026-05-05', $daily->date?->format('Y-m-d'));
        $this->assertStringContainsString('إيداع بنكي', (string) $daily->description);
        $this->assertSame(2, $daily->items()->count());
    }

    public function test_updating_bank_direct_replaces_daily_entry_without_orphan_headers(): void
    {
        $user = User::query()->first();
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();
        $service = app(DirectCashTransactionService::class);

        $txn = $service->createBank([
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'receipt',
            'amount' => 1000,
            'date' => '2026-05-05',
            'notes' => 'أولي',
        ]);

        $oldDailyIds = AccountEntry::query()
            ->where('entry_batch_code', $txn->entry_batch_code)
            ->pluck('daily_entry_id')
            ->unique()
            ->all();

        $service->updateBankDirect($txn->id, [
            'bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'type' => 'receipt',
            'amount' => 1500,
            'date' => '2026-05-06',
            'notes' => 'بعد التعديل',
        ]);

        $this->assertSame(0, DailyEntry::query()->whereIn('id', $oldDailyIds)->count());

        $freshLines = AccountEntry::query()
            ->where('entry_batch_code', $txn->entry_batch_code)
            ->get();
        $this->assertCount(2, $freshLines);
        $newDaily = DailyEntry::query()->find($freshLines->first()->daily_entry_id);
        $this->assertNotNull($newDaily);
        $this->assertSame('2026-05-06', $newDaily->date?->format('Y-m-d'));
        $this->assertEqualsWithDelta(1500.0, (float) $freshLines->sum('debit'), 0.01);
    }

    public function test_backfill_attaches_daily_entry_to_legacy_bank_gl_without_duplicating_amounts(): void
    {
        $user = User::query()->first();
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();
        $batch = 'BANK-DW-LEGACY-' . $this->codePrefix;

        AccountEntry::create([
            'tree_account_id' => $bank->asset_id,
            'debit' => 500,
            'credit' => 0,
            'description' => 'إيداع بنكي [' . $batch . ']',
            'entry_batch_code' => $batch,
            'created_at' => '2026-05-05',
            'updated_at' => '2026-05-05',
        ]);
        AccountEntry::create([
            'tree_account_id' => $counter->id,
            'debit' => 0,
            'credit' => 500,
            'description' => 'إيداع بنكي [' . $batch . ']',
            'entry_batch_code' => $batch,
            'created_at' => '2026-05-05',
            'updated_at' => '2026-05-05',
        ]);

        $created = app(DirectCashTransactionService::class)->backfillMissingJournalHeaders($batch);
        $this->assertSame(1, $created);

        $lines = AccountEntry::query()->where('entry_batch_code', $batch)->get();
        $this->assertCount(2, $lines);
        $this->assertTrue($lines->every(fn (AccountEntry $e) => $e->daily_entry_id !== null));
        $this->assertEqualsWithDelta(500.0, (float) $lines->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $lines->sum('credit'), 0.01);

        $this->assertSame(0, app(DirectCashTransactionService::class)->backfillMissingJournalHeaders($batch));
    }

    public function test_daily_ledger_resolves_user_name_for_legacy_bank_batch(): void
    {
        $user = User::query()->first();
        $this->actingAs($user);

        $bank = $this->seedBank();
        $counter = $this->seedCounterAccount();
        $batch = DirectCashTransactionService::BANK_BATCH_PREFIX . 'LEDGER-' . $this->codePrefix;

        \App\Models\BankTransaction::create([
            'date' => '2026-05-05',
            'type' => 'withdrawal',
            'from_bank_id' => $bank->id,
            'counter_account_id' => $counter->id,
            'amount' => 75200,
            'notes' => 'سحب ا حاتم من البنك',
            'entry_batch_code' => $batch,
            'user_id' => $user->id,
        ]);

        AccountEntry::create([
            'tree_account_id' => $bank->asset_id,
            'debit' => 0,
            'credit' => 75200,
            'description' => 'سحب بنكي [' . $batch . '] - سحب ا حاتم من البنك',
            'entry_batch_code' => $batch,
            'created_at' => '2026-05-05',
            'updated_at' => '2026-05-05',
        ]);
        AccountEntry::create([
            'tree_account_id' => $counter->id,
            'debit' => 75200,
            'credit' => 0,
            'description' => 'سحب بنكي [' . $batch . '] - سحب ا حاتم من البنك',
            'entry_batch_code' => $batch,
            'created_at' => '2026-05-05',
            'updated_at' => '2026-05-05',
        ]);

        $request = \Illuminate\Http\Request::create('/accounting/reports/daily-ledger', 'GET', [
            'date_from' => '2026-05-05',
            'date_to' => '2026-05-05',
            'account_id' => $bank->asset_id,
            'per_page' => 50,
        ]);
        $response = app(\App\Http\Controllers\V2\Accounting\AccountingReportController::class)
            ->dailyLedger($request);
        $payload = $response->getData(true);
        $rows = $payload['data']['data'] ?? [];
        $matched = collect($rows)->firstWhere('entry_batch_code', $batch);

        $this->assertNotNull($matched);
        $this->assertSame($user->name, $matched['journal_user_name'] ?? null);
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
