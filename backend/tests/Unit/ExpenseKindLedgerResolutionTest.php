<?php

namespace Tests\Unit;

use App\Models\AccountEntry;
use App\Models\ExpenseKind;
use App\Models\TreeAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExpenseKindLedgerResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'EK' . substr(str_replace('.', '', uniqid('', true)), -8);
    }

    public function test_resolve_expense_debit_uses_explicit_linked_account_regardless_of_type(): void
    {
        $assetAccount = TreeAccount::create([
            'code' => $this->codePrefix . '1001',
            'name' => 'حساب أصول مرتبط بفئة مصروف',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $kind = ExpenseKind::create([
            'expense_type' => 'مصروف تشغيل',
            'expense_kind' => 'اختبار ربط شجرة',
            'tree_account_id' => $assetAccount->id,
        ]);

        $resolved = TreeAccount::resolveExpenseDebitForKind($kind, $kind->expense_type);

        $this->assertNotNull($resolved);
        $this->assertSame($assetAccount->id, $resolved->id);
        $this->assertSame('asset', $resolved->type);
    }

    public function test_expense_posting_updates_linked_tree_account_balance(): void
    {
        $debitAccount = TreeAccount::create([
            'code' => $this->codePrefix . '5001',
            'name' => 'حساب مدين مخصص',
            'type' => 'expense',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $creditAccount = TreeAccount::create([
            'code' => $this->codePrefix . '1002',
            'name' => 'حساب دائن مصدر دفع',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $kind = ExpenseKind::create([
            'expense_type' => 'مصروف تشغيل',
            'expense_kind' => 'اختبار قيد',
            'tree_account_id' => $debitAccount->id,
        ]);

        $resolved = TreeAccount::resolveExpenseDebitForKind($kind, $kind->expense_type);
        $this->assertSame($debitAccount->id, $resolved->id);

        $amount = 150.50;
        $batchCode = 'EXP-TEST-' . now()->format('YmdHis');

        AccountEntry::create([
            'tree_account_id' => $debitAccount->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => 'مصروف - اختبار - مصدر - REF-1',
            'order_id' => null,
            'entry_batch_code' => $batchCode,
        ]);

        AccountEntry::create([
            'tree_account_id' => $creditAccount->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => 'صرف مصروف - REF-1',
            'order_id' => null,
            'entry_batch_code' => $batchCode,
        ]);

        $accounting = app(AccountingService::class);
        $accounting->updateAccountHierarchyBalances($debitAccount->id);
        $accounting->updateAccountHierarchyBalances($creditAccount->id);

        $debitAccount->refresh();
        $creditAccount->refresh();

        $this->assertEqualsWithDelta($amount, (float) $debitAccount->debit_balance, 0.01);
        $this->assertEqualsWithDelta($amount, (float) $debitAccount->balance, 0.01);
        $this->assertEqualsWithDelta($amount, (float) $creditAccount->credit_balance, 0.01);
        $this->assertEqualsWithDelta(-$amount, (float) $creditAccount->balance, 0.01);
    }

    public function test_auto_mode_creates_expense_account_when_tree_account_id_is_null(): void
    {
        $parent = TreeAccount::ensureCashOutExpenseParent();

        $kind = ExpenseKind::create([
            'expense_type' => 'مصروف تشغيل',
            'expense_kind' => 'فئة تلقائية ' . $this->codePrefix,
            'tree_account_id' => null,
        ]);

        $resolved = TreeAccount::ensureExpenseKindLedgerAccount($kind, $kind->expense_type);

        $this->assertNotNull($resolved);
        $this->assertSame('expense', $resolved->type);
        $this->assertTrue(TreeAccount::isDescendantOf($resolved, (int) $parent->id));

        $kind->refresh();
        $this->assertSame($resolved->id, (int) $kind->tree_account_id);
    }
}
