<?php

namespace Tests\Feature;

use App\Models\AccountEntry;
use App\Models\TreeAccount;
use App\Services\Accounting\TreeAccountBalanceRebuildService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TreeAccountBalanceRebuildTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rebuild_does_not_zero_leaf_accounts_at_parent_levels(): void
    {
        $root = TreeAccount::create([
            'code' => 'TR1000',
            'name' => 'أصول اختبار',
            'type' => 'asset',
            'level' => 1,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $leaf = TreeAccount::create([
            'code' => 'TR10001',
            'name' => 'خزينة اختبار',
            'type' => 'asset',
            'level' => 2,
            'parent_id' => $root->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        AccountEntry::create([
            'tree_account_id' => $leaf->id,
            'debit' => 500,
            'credit' => 0,
            'description' => 'test',
        ]);

        app(TreeAccountBalanceRebuildService::class)->rebuildAll();

        $leaf->refresh();
        $root->refresh();

        $this->assertSame(500.0, (float) $leaf->balance);
        $this->assertSame(500.0, (float) $root->balance);
    }
}
