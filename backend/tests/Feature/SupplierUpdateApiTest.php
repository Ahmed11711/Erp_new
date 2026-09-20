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

class SupplierUpdateApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = config('auth.defaults.guard');
        $viewPerm = Permission::query()->firstOrCreate(
            ['slug' => 'suppliers.view', 'guard_name' => $guard],
            ['name' => 'View Suppliers', 'module' => 'suppliers']
        );

        $role = Role::query()->firstOrCreate(
            ['slug' => 'test-supplier-update', 'guard_name' => $guard],
            ['name' => 'Test Supplier Update']
        );
        $role->syncPermissions([$viewPerm->id]);

        $this->user = User::factory()->create(['department' => 'Test Dept']);
        $this->user->roles()->sync([$role->id]);
        app(RbacStampService::class)->bumpUser($this->user->id);
    }

    public function test_show_returns_supplier(): void
    {
        $supplier = $this->makeSupplier('مورد للعرض');

        $response = $this->actingAs($this->user, 'api')->getJson("/api/suppliers/{$supplier->id}");
        $response->assertOk()
            ->assertJsonPath('supplier_name', 'مورد للعرض');
    }

    public function test_update_changes_supplier_and_tree_account_name(): void
    {
        $tree = TreeAccount::create([
            'code' => 'T'.uniqid(),
            'name' => 'اسم قديم',
            'type' => 'liability',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'level' => 1,
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'اسم قديم',
            'supplier_phone' => '01000000000',
            'supplier_address' => 'عنوان قديم',
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $tree->id,
        ]);

        $response = $this->actingAs($this->user, 'api')->putJson("/api/suppliers/{$supplier->id}", [
            'supplier_name' => 'اسم جديد',
            'supplier_phone' => '01111111111',
            'supplier_address' => 'عنوان جديد',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('supplier.supplier_name', 'اسم جديد');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'supplier_name' => 'اسم جديد',
            'supplier_phone' => '01111111111',
            'supplier_address' => 'عنوان جديد',
        ]);

        $this->assertDatabaseHas('tree_accounts', [
            'id' => $tree->id,
            'name' => 'اسم جديد',
        ]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create([
            'supplier_name' => $name,
            'balance' => 0,
            'last_balance' => 0,
        ]);
    }
}
