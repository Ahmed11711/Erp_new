<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Rbac\RbacStampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupplierDeletionApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = config('auth.defaults.guard');
        $deletePerm = Permission::query()->firstOrCreate(
            ['slug' => 'suppliers.delete', 'guard_name' => $guard],
            ['name' => 'Delete Suppliers', 'module' => 'suppliers']
        );
        $purgePerm = Permission::query()->firstOrCreate(
            ['slug' => 'suppliers.purge_all', 'guard_name' => $guard],
            ['name' => 'Purge All Suppliers', 'module' => 'suppliers']
        );
        $viewPerm = Permission::query()->firstOrCreate(
            ['slug' => 'suppliers.view', 'guard_name' => $guard],
            ['name' => 'View Suppliers', 'module' => 'suppliers']
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => 'test-supplier-delete', 'guard_name' => $guard],
            ['name' => 'Test Supplier Delete']
        );
        $role->syncPermissions([$deletePerm->id, $purgePerm->id, $viewPerm->id]);

        $this->user = User::factory()->create(['department' => 'Test Dept']);
        $this->user->roles()->sync([$role->id]);
        app(RbacStampService::class)->bumpUser($this->user->id);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        $other = User::factory()->create(['department' => 'No Perms']);
        $supplier = $this->makeDeletableSupplier();

        $response = $this->actingAs($other, 'api')->deleteJson("/api/suppliers/{$supplier->id}");
        $response->assertStatus(403);
    }

    public function test_destroy_deletes_supplier_and_tree_account(): void
    {
        $supplier = $this->makeDeletableSupplier();
        $treeId = (int) $supplier->tree_account_id;

        $response = $this->actingAs($this->user, 'api')->deleteJson("/api/suppliers/{$supplier->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('tree_accounts', ['id' => $treeId]);
    }

    public function test_purge_all_requires_permission(): void
    {
        $other = User::factory()->create(['department' => 'No Perms']);

        $response = $this->actingAs($other, 'api')->postJson('/api/suppliers/purge-all', ['confirm' => true]);
        $response->assertStatus(403);
    }

    public function test_purge_all_deletes_all_suppliers_and_tree_accounts(): void
    {
        $s1 = $this->makeDeletableSupplier('مورد 1');
        $s2 = $this->makeDeletableSupplier('مورد 2');

        $response = $this->actingAs($this->user, 'api')->postJson('/api/suppliers/purge-all', ['confirm' => true]);
        $response->assertOk();

        $this->assertDatabaseMissing('suppliers', ['id' => $s1->id]);
        $this->assertDatabaseMissing('suppliers', ['id' => $s2->id]);
        $this->assertDatabaseMissing('tree_accounts', ['id' => $s1->tree_account_id]);
        $this->assertDatabaseMissing('tree_accounts', ['id' => $s2->tree_account_id]);
    }

    public function test_bulk_delete_requires_permission(): void
    {
        $other = User::factory()->create(['department' => 'No Perms']);
        $supplier = $this->makeDeletableSupplier();

        $response = $this->actingAs($other, 'api')->postJson('/api/suppliers/bulk-delete', [
            'ids' => [$supplier->id],
        ]);
        $response->assertStatus(403);
    }

    public function test_bulk_delete_deletes_selected_suppliers(): void
    {
        $s1 = $this->makeDeletableSupplier('مورد 1');
        $s2 = $this->makeDeletableSupplier('مورد 2');
        $s3 = $this->makeDeletableSupplier('مورد 3');

        $response = $this->actingAs($this->user, 'api')->postJson('/api/suppliers/bulk-delete', [
            'ids' => [$s1->id, $s3->id],
        ]);
        $response->assertOk()
            ->assertJsonPath('results.deleted', [$s1->id, $s3->id]);

        $this->assertDatabaseMissing('suppliers', ['id' => $s1->id]);
        $this->assertDatabaseMissing('suppliers', ['id' => $s3->id]);
        $this->assertDatabaseHas('suppliers', ['id' => $s2->id]);
    }

    public function test_bulk_delete_reports_failures_for_blocked_suppliers(): void
    {
        $deletable = $this->makeDeletableSupplier('قابل للحذف');
        $withBalance = Supplier::create([
            'supplier_name' => 'مورد برصيد',
            'balance' => 100,
            'last_balance' => 0,
            'tree_account_id' => TreeAccount::create([
                'code' => 'T'.uniqid(),
                'name' => 'حساب مورد برصيد',
                'type' => 'liability',
                'level' => 3,
                'parent_id' => null,
                'balance' => 100,
                'debit_balance' => 0,
                'credit_balance' => 100,
            ])->id,
        ]);

        $response = $this->actingAs($this->user, 'api')->postJson('/api/suppliers/bulk-delete', [
            'ids' => [$deletable->id, $withBalance->id],
        ]);
        $response->assertOk()
            ->assertJsonPath('results.deleted', [$deletable->id, $withBalance->id]);

        $this->assertDatabaseMissing('suppliers', ['id' => $deletable->id]);
        $this->assertDatabaseMissing('suppliers', ['id' => $withBalance->id]);
    }

    private function makeDeletableSupplier(string $name = 'مورد للحذف'): Supplier
    {
        $leaf = TreeAccount::create([
            'code' => 'T'.uniqid(),
            'name' => $name,
            'type' => 'liability',
            'level' => 3,
            'parent_id' => null,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        return Supplier::create([
            'supplier_name' => $name,
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $leaf->id,
        ]);
    }
}
