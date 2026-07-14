<?php

namespace Tests\Feature;

use App\Models\TreeAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TreeAccountSoftDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_delete_moves_account_and_children_to_trash(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $parent = TreeAccount::create([
            'code' => 'SD'.uniqid(),
            'name' => 'أب للحذف',
            'type' => 'asset',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $child = TreeAccount::create([
            'code' => 'SDC'.uniqid(),
            'name' => 'فرع للحذف',
            'type' => 'asset',
            'parent_id' => $parent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$parent->id}");
        $response->assertOk();

        $this->assertSoftDeleted('tree_accounts', ['id' => $parent->id]);
        $this->assertSoftDeleted('tree_accounts', ['id' => $child->id]);
        $this->assertNull(TreeAccount::find($parent->id));
        $this->assertNotNull(TreeAccount::onlyTrashed()->find($parent->id));

        $this->assertDatabaseHas('tree_account_audits', [
            'tree_account_id' => $parent->id,
            'action' => 'deleted',
            'performed_by' => $user->id,
        ]);
    }

    public function test_trash_lists_deleted_subtree_roots_only(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $parent = TreeAccount::create([
            'code' => 'TR'.uniqid(),
            'name' => 'أب سلة',
            'type' => 'liability',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $child = TreeAccount::create([
            'code' => 'TRC'.uniqid(),
            'name' => 'فرع سلة',
            'type' => 'liability',
            'parent_id' => $parent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);

        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$parent->id}")->assertOk();

        $trash = $this->actingAs($user, 'api')->getJson('/api/tree_accounts/trash');
        $trash->assertOk();

        $ids = collect($trash->json('data'))->pluck('id')->all();
        $this->assertContains($parent->id, $ids);
        $this->assertNotContains($child->id, $ids);
    }

    public function test_restore_brings_back_account_and_children(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $parent = TreeAccount::create([
            'code' => 'RS'.uniqid(),
            'name' => 'أب استرجاع',
            'type' => 'expense',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $child = TreeAccount::create([
            'code' => 'RSC'.uniqid(),
            'name' => 'فرع استرجاع',
            'type' => 'expense',
            'parent_id' => $parent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);

        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$parent->id}")->assertOk();

        $restore = $this->actingAs($user, 'api')->postJson("/api/tree_accounts/{$parent->id}/restore");
        $restore->assertOk();

        $this->assertDatabaseHas('tree_accounts', [
            'id' => $parent->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('tree_accounts', [
            'id' => $child->id,
            'deleted_at' => null,
        ]);
        $this->assertNotNull(TreeAccount::find($parent->id));
        $this->assertNotNull(TreeAccount::find($child->id));

        $this->assertDatabaseHas('tree_account_audits', [
            'tree_account_id' => $parent->id,
            'action' => 'restored',
            'performed_by' => $user->id,
        ]);
    }

    public function test_cannot_restore_child_while_parent_trashed(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $parent = TreeAccount::create([
            'code' => 'NP'.uniqid(),
            'name' => 'أب محظور',
            'type' => 'revenue',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $child = TreeAccount::create([
            'code' => 'NPC'.uniqid(),
            'name' => 'فرع محظور',
            'type' => 'revenue',
            'parent_id' => $parent->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 2,
        ]);

        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$parent->id}")->assertOk();

        $restore = $this->actingAs($user, 'api')->postJson("/api/tree_accounts/{$child->id}/restore");
        $restore->assertStatus(422);
        $this->assertSoftDeleted('tree_accounts', ['id' => $child->id]);
    }

    public function test_force_delete_removes_permanently(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $account = TreeAccount::create([
            'code' => 'FD'.uniqid(),
            'name' => 'حذف نهائي',
            'type' => 'asset',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $id = $account->id;
        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$id}")->assertOk();
        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$id}/force")->assertOk();

        $this->assertDatabaseMissing('tree_accounts', ['id' => $id]);
        $this->assertNull(TreeAccount::withTrashed()->find($id));
    }

    public function test_account_statement_works_for_soft_deleted_account(): void
    {
        $user = User::factory()->create(['department' => 'Admin']);

        $account = TreeAccount::create([
            'code' => 'AS'.uniqid(),
            'name' => 'كشف محذوف',
            'type' => 'settlement',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$account->id}")->assertOk();
        $this->assertNull(TreeAccount::find($account->id));

        $response = $this->actingAs($user, 'api')->getJson(
            '/api/accounting/reports/account-statement?account_id='.$account->id
        );

        $response->assertOk();
        $response->assertJsonPath('account.id', $account->id);
        $response->assertJsonPath('account.type', 'settlement');
        $this->assertIsArray($response->json('entries'));
    }

    public function test_trash_restore_force_forbidden_for_non_admin(): void
    {
        $admin = User::factory()->create(['department' => 'Admin']);
        $finance = User::factory()->create(['department' => 'Financial Accounts']);

        $account = TreeAccount::create([
            'code' => 'NA'.uniqid(),
            'name' => 'حساب غير أدمن',
            'type' => 'asset',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $this->actingAs($admin, 'api')->deleteJson("/api/tree_accounts/{$account->id}")->assertOk();

        $this->actingAs($finance, 'api')->getJson('/api/tree_accounts/trash')->assertForbidden();
        $this->actingAs($finance, 'api')->postJson("/api/tree_accounts/{$account->id}/restore")->assertForbidden();
        $this->actingAs($finance, 'api')->deleteJson("/api/tree_accounts/{$account->id}/force")->assertForbidden();

        $this->assertSoftDeleted('tree_accounts', ['id' => $account->id]);
    }
}
