<?php

namespace Tests\Feature;

use App\Models\TreeAccount;
use App\Models\TreeAccountAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TreeAccountAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_update_delete_logs_authenticated_user(): void
    {
        $user = User::factory()->create();

        $parent = TreeAccount::create([
            'code' => 'TA'.uniqid(),
            'name' => 'حساب أب اختبار',
            'type' => 'asset',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $createResponse = $this->actingAs($user, 'api')->postJson('/api/tree_accounts', [
            'name' => 'حساب فرعي اختبار',
            'type' => 'asset',
            'parent_id' => $parent->id,
        ]);

        $createResponse->assertCreated();
        $accountId = (int) $createResponse->json('data.id');
        $this->assertNotSame(0, $accountId);

        $account = TreeAccount::findOrFail($accountId);
        $this->assertSame($user->id, (int) $account->created_by);
        $this->assertDatabaseHas('tree_account_audits', [
            'tree_account_id' => $accountId,
            'action' => 'created',
            'performed_by' => $user->id,
        ]);

        $updateResponse = $this->actingAs($user, 'api')->putJson("/api/tree_accounts/{$accountId}", [
            'name' => 'حساب فرعي معدّل',
        ]);

        $updateResponse->assertOk();
        $account->refresh();
        $this->assertSame($user->id, (int) $account->updated_by);

        $updateAudit = TreeAccountAudit::query()
            ->where('tree_account_id', $accountId)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($updateAudit);
        $this->assertSame($user->id, (int) $updateAudit->performed_by);
        $this->assertSame('حساب فرعي اختبار', $updateAudit->changes['name']['old'] ?? null);
        $this->assertSame('حساب فرعي معدّل', $updateAudit->changes['name']['new'] ?? null);

        $deleteResponse = $this->actingAs($user, 'api')->deleteJson("/api/tree_accounts/{$accountId}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('tree_accounts', ['id' => $accountId]);
        $this->assertDatabaseHas('tree_account_audits', [
            'tree_account_id' => $accountId,
            'action' => 'deleted',
            'performed_by' => $user->id,
            'account_name' => 'حساب فرعي معدّل',
        ]);
    }

    public function test_balance_only_update_does_not_create_audit_entry(): void
    {
        $user = User::factory()->create();

        $account = TreeAccount::create([
            'code' => 'TB'.uniqid(),
            'name' => 'حساب رصيد',
            'type' => 'asset',
            'balance' => 100,
            'debit_balance' => 100,
            'credit_balance' => 0,
            'level' => 1,
            'created_by' => $user->id,
        ]);

        $before = TreeAccountAudit::query()->where('tree_account_id', $account->id)->count();

        $this->actingAs($user, 'api');
        $account->balance = 250;
        $account->save();

        $after = TreeAccountAudit::query()->where('tree_account_id', $account->id)->count();
        $this->assertSame($before, $after);
    }
}
