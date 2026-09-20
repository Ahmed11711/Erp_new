<?php

namespace Tests\Unit;

use App\Models\ShippingCompany;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Accounting\ExpenseDebitOperationalSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpenseDebitShippingCompanySyncTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->codePrefix = 'ES' . substr(str_replace('.', '', uniqid('', true)), -8);

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->actingAs($user);
    }

    public function test_expense_debit_on_shipping_receivable_increases_operational_balance(): void
    {
        [$company, $receivable] = $this->seedShippingCompanyWithReceivable(150.0);

        $sync = app(ExpenseDebitOperationalSyncService::class);
        $sync->syncDebitLine(
            (int) $receivable->id,
            40.0,
            'مصروف - عهدة - EXP-TEST-1',
            'EXP-TEST-1-DR-' . $receivable->id,
            '2026-07-13',
            false
        );

        $company->refresh();
        $this->assertSame(190.0, (float) $company->balance);

        $detail = DB::table('shipping_company_details')
            ->where('ref', 'EXP-TEST-1-DR-' . $receivable->id)
            ->first();

        $this->assertNotNull($detail);
        $this->assertSame((int) $company->id, (int) $detail->shipping_company_id);
        $this->assertSame('مصروف', $detail->status);
        $this->assertSame(40.0, (float) $detail->amount);
        $this->assertSame(1, (int) $detail->is_done);
    }

    public function test_expense_debit_reverse_restores_shipping_balance_and_removes_detail(): void
    {
        [$company, $receivable] = $this->seedShippingCompanyWithReceivable(80.0);
        $ref = 'EXP-TEST-2-DR-' . $receivable->id;

        $sync = app(ExpenseDebitOperationalSyncService::class);
        $sync->syncDebitLine((int) $receivable->id, 25.0, 'مصروف', $ref, '2026-07-13', false);
        $company->refresh();
        $this->assertSame(105.0, (float) $company->balance);

        $sync->syncDebitLine((int) $receivable->id, 25.0, 'مصروف', $ref, '2026-07-13', true);
        $company->refresh();

        $this->assertSame(80.0, (float) $company->balance);
        $this->assertSame(0, DB::table('shipping_company_details')->where('ref', $ref)->count());
    }

    public function test_expense_debit_on_payable_tree_account_does_not_touch_operational_balance(): void
    {
        $payable = TreeAccount::create([
            'code' => $this->codePrefix . '2',
            'name' => 'مستحق مندوب ' . $this->codePrefix,
            'type' => 'liability',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $company = ShippingCompany::create([
            'name' => 'مندوب payable ' . $this->codePrefix,
            'type' => 'مندوب',
            'tree_account_id' => $payable->id,
            'receivable_tree_account_id' => null,
        ]);
        $company->balance = 50.0;
        $company->save();

        $sync = app(ExpenseDebitOperationalSyncService::class);
        $sync->syncDebitLine(
            (int) $payable->id,
            10.0,
            'مصروف',
            'EXP-TEST-3-DR-' . $payable->id,
            '2026-07-13',
            false
        );

        $company->refresh();
        $this->assertSame(50.0, (float) $company->balance);
        $this->assertSame(
            0,
            DB::table('shipping_company_details')->where('shipping_company_id', $company->id)->count()
        );
    }

    /**
     * @return array{0: ShippingCompany, 1: TreeAccount}
     */
    private function seedShippingCompanyWithReceivable(float $openingBalance): array
    {
        $receivable = TreeAccount::create([
            'code' => $this->codePrefix . '1',
            'name' => 'ذمم مندوب ' . $this->codePrefix,
            'type' => 'asset',
            'level' => 4,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $company = ShippingCompany::create([
            'name' => 'مندوب ' . $this->codePrefix,
            'type' => 'مندوب',
            'receivable_tree_account_id' => $receivable->id,
        ]);
        $company->balance = $openingBalance;
        $company->save();

        return [$company, $receivable];
    }
}
