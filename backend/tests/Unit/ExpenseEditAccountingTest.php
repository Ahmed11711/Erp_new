<?php

namespace Tests\Unit;

use App\Http\Controllers\ExpenseController;
use App\Models\Bank;
use App\Models\Expense;
use App\Models\ExpenseKind;
use App\Models\ExpenseLine;
use App\Models\TreeAccount;
use App\Models\User;
use App\Models\AccountEntry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ExpenseEditAccountingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'EE' . substr(str_replace('.', '', uniqid('', true)), -8);

        $user = User::query()->first();
        if ($user) {
            Auth::login($user);
        }
    }

    public function test_edit_expense_reverses_old_gl_and_posts_new_entries_in_place(): void
    {
        $debitOld = TreeAccount::create([
            'code' => $this->codePrefix . '5001',
            'name' => 'مصروف قديم',
            'type' => 'expense',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $debitNew = TreeAccount::create([
            'code' => $this->codePrefix . '5002',
            'name' => 'مصروف جديد',
            'type' => 'expense',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
        $creditAccount = TreeAccount::create([
            'code' => $this->codePrefix . '1001',
            'name' => 'بنك تجريبي',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $bank = Bank::create([
            'name' => 'بنك تعديل مصروف ' . $this->codePrefix,
            'type' => 'bank',
            'balance' => 1000,
            'usage' => 'general',
            'asset_id' => $creditAccount->id,
        ]);

        $kindOld = ExpenseKind::create([
            'expense_type' => 'مصروف تشغيل',
            'expense_kind' => 'فئة قديمة ' . $this->codePrefix,
            'tree_account_id' => $debitOld->id,
        ]);
        $kindNew = ExpenseKind::create([
            'expense_type' => 'مصروف ادارى',
            'expense_kind' => 'فئة جديدة ' . $this->codePrefix,
            'tree_account_id' => $debitNew->id,
        ]);

        $expense = Expense::create([
            'expense_type' => 'مصروف تشغيل',
            'payment_type' => 'bank',
            'bank_id' => $bank->id,
            'kind_id' => $kindOld->id,
            'expens_statement' => 'مصروف أصلي',
            'amount' => 100,
            'note' => 'ملاحظة',
            'address' => 'مصروف أصلي',
            'expense_image' => '',
            'user_id' => Auth::id() ?? 1,
            'status' => 0,
        ]);
        $expense->refresh();

        ExpenseLine::create([
            'expense_id' => $expense->id,
            'expense_type' => 'مصروف تشغيل',
            'kind_id' => $kindOld->id,
            'amount' => 100,
            'sort_order' => 0,
        ]);

        $batchCode = 'EXP-' . now()->format('YmdHis');
        AccountEntry::create([
            'tree_account_id' => $debitOld->id,
            'debit' => 100,
            'credit' => 0,
            'description' => 'مصروف - فئة قديمة - بنك - ' . $expense->expense_number,
            'entry_batch_code' => $batchCode,
        ]);
        AccountEntry::create([
            'tree_account_id' => $creditAccount->id,
            'debit' => 0,
            'credit' => 100,
            'description' => 'صرف مصروف - ' . $expense->expense_number,
            'entry_batch_code' => $batchCode,
        ]);
        $bank->update(['balance' => 900]);

        $request = Request::create('/api/editexpense/' . $expense->id, 'POST', [
            'payment_type' => 'bank',
            'bank_id' => $bank->id,
            'expens_statement' => 'مصروف معدّل',
            'amount' => 250,
            'note' => 'ملاحظة معدّلة',
            'address' => 'مصروف معدّل',
            'created_at' => now()->format('Y-m-d H:i:s'),
            'lines' => json_encode([[
                'expense_type' => 'مصروف ادارى',
                'kind_id' => $kindNew->id,
                'amount' => 250,
            ]]),
        ]);

        $controller = app(ExpenseController::class);
        $response = $controller->editExpense($expense->id, $request);
        $this->assertSame(200, $response->getStatusCode());

        $expense->refresh();
        $this->assertSame(250.0, (float) $expense->amount);
        $this->assertSame($kindNew->id, (int) $expense->kind_id);
        $this->assertSame('مصروف معدّل', $expense->expens_statement);

        $bank->refresh();
        $this->assertEqualsWithDelta(750.0, (float) $bank->balance, 0.01);

        $this->assertTrue(
            AccountEntry::where('description', 'like', 'عكس - %' . $expense->expense_number)->exists()
        );
        $this->assertTrue(
            AccountEntry::where('tree_account_id', $debitNew->id)
                ->where('debit', 250)
                ->where('description', 'like', '%' . $expense->expense_number)
                ->exists()
        );
        $this->assertTrue(
            AccountEntry::where('tree_account_id', $creditAccount->id)
                ->where('credit', 250)
                ->where('description', 'like', '%' . $expense->expense_number)
                ->exists()
        );
    }
}
