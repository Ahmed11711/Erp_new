<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Models\TreeAccount;
use App\Services\Suppliers\SupplierDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SupplierDeletionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deletes_supplier_and_tree_account_and_rebuilds_parent(): void
    {
        $parent = TreeAccount::create([
            'code' => 'Z'.uniqid(),
            'name' => 'موردين اختبار',
            'type' => 'liability',
            'level' => 2,
            'parent_id' => null,
            'balance' => 100,
            'debit_balance' => 0,
            'credit_balance' => 100,
        ]);

        $leaf = TreeAccount::create([
            'code' => 'Z'.uniqid(),
            'name' => 'مورد للحذف',
            'type' => 'liability',
            'level' => 3,
            'parent_id' => $parent->id,
            'balance' => 100,
            'debit_balance' => 0,
            'credit_balance' => 100,
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'مورد للحذف',
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $leaf->id,
        ]);

        app(SupplierDeletionService::class)->delete($supplier);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('tree_accounts', ['id' => $leaf->id]);
        $this->assertSame(0.0, (float) $parent->fresh()->balance);
        $this->assertSame(0.0, (float) $parent->fresh()->credit_balance);
    }

    public function test_deletes_supplier_with_balance(): void
    {
        $leaf = TreeAccount::create([
            'code' => 'Z'.uniqid(),
            'name' => 'مورد برصيد',
            'type' => 'liability',
            'level' => 3,
            'parent_id' => null,
            'balance' => 3150,
            'debit_balance' => 0,
            'credit_balance' => 3150,
        ]);

        $supplier = Supplier::create([
            'supplier_name' => 'مورد برصيد',
            'balance' => 3150,
            'last_balance' => 0,
            'tree_account_id' => $leaf->id,
        ]);

        app(SupplierDeletionService::class)->delete($supplier);

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseMissing('tree_accounts', ['id' => $leaf->id]);
    }
}
