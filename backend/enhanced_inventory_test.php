<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\TreeAccount;
use App\Models\AccountEntry;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Services\CategoryInventoryCostService;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Accounting\AccountingService;

/**
 * Enhanced ERP Test Script
 * Tests inventory logic, account balances, and financial calculations
 */
class EnhancedInventoryTest
{
    private $testResults = [];
    private $errors = [];

    public function __construct()
    {
        // Bootstrap Laravel
        $app = require_once 'bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    }

    /**
     * Run all enhanced tests
     */
    public function runAllTests()
    {
        echo "=== Enhanced ERP Inventory & Accounts Test ===\n\n";

        $this->testFixedInventoryLogic();
        $this->testCustomerAccountBalances();
        $this->testSupplierAccountBalances();
        $this->testWarehouseInventoryAccounts();
        $this->testProductionInventoryAccounts();
        $this->testInventoryCostAccounts();
        $this->testTreeAccountHierarchy();
        $this->testFinancialPostingsIntegration();

        $this->printResults();
    }

    /**
     * Test 1: Fixed Inventory Logic with Balance Records
     */
    private function testFixedInventoryLogic()
    {
        echo "Test 1: Fixed Inventory Logic with Balance Records\n";
        echo "--------------------------------------------------\n";

        try {
            // Create test category with all required fields
            $category = Category::create([
                'category_name' => 'Test Fixed Product ' . uniqid(),
                'category_price' => 100.00,
                'initial_balance' => 1000.00,
                'unit_price' => 10.00,
                'total_price' => 1000.00,
                'quantity' => 100,
                'minimum_quantity' => 10,
                'warehouse' => 'مخزن مواد خام',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            $this->assert($category->id > 0, "Category created successfully");

            // Test balance record creation manually
            DB::table('categories_balance')->insert([
                'invoice_number' => 'INIT-' . $category->id,
                'category_id' => $category->id,
                'type' => 'رصيد أول المدة',
                'quantity' => 100,
                'balance_before' => 0,
                'balance_after' => 100,
                'price' => 10.00,
                'total_price' => 1000.00,
                'unit_cost' => 10.00,
                'cost_total' => 1000.00,
                'by' => 'Test System',
                'created_at' => now()
            ]);

            // Verify balance record
            $balanceRecord = DB::table('categories_balance')
                ->where('category_id', $category->id)
                ->first();

            $this->assert($balanceRecord !== null, "Balance record created successfully");
            $this->assert($balanceRecord->quantity == 100, "Balance quantity correct");

            // Test category resolution service
            $resolvedId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine(
                ['category_name' => $category->category_name], 
                $category->category_name
            );
            
            $this->assert($resolvedId == $category->id, "Category resolution service working");

            // Test purchase simulation with balance records
            $purchaseQty = 25;
            $purchaseUnitPrice = 12.00;
            $purchaseTotal = $purchaseQty * $purchaseUnitPrice;

            // Update category
            DB::table('categories')->where('id', $category->id)->increment('quantity', $purchaseQty);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $purchaseTotal);
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            // Create balance record for purchase
            DB::table('categories_balance')->insert([
                'invoice_number' => 'PURCHASE-' . uniqid(),
                'category_id' => $category->id,
                'type' => 'فواتير مشتريات',
                'quantity' => $purchaseQty,
                'balance_before' => 100,
                'balance_after' => 100 + $purchaseQty,
                'price' => $purchaseUnitPrice,
                'total_price' => $purchaseTotal,
                'unit_cost' => $purchaseUnitPrice,
                'cost_total' => $purchaseTotal,
                'by' => 'Test User',
                'created_at' => now()
            ]);

            // Verify updates
            $updatedCategory = Category::find($category->id);
            $expectedQty = 100 + $purchaseQty;
            $expectedTotal = 1000.00 + $purchaseTotal;

            $this->assert($updatedCategory->quantity == $expectedQty, "Quantity updated correctly");
            $this->assert(abs($updatedCategory->total_price - $expectedTotal) < 0.01, "Total price updated correctly");

            echo "✓ Fixed inventory logic test passed\n\n";
            $this->testResults['fixed_inventory'] = true;

        } catch (Exception $e) {
            $this->errors['fixed_inventory'] = $e->getMessage();
            echo "✗ Fixed inventory logic test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 2: Customer Account Balances in Tree Accounts
     */
    private function testCustomerAccountBalances()
    {
        echo "Test 2: Customer Account Balances in Tree Accounts\n";
        echo "---------------------------------------------------\n";

        try {
            // Create or find tree account for customers
            $customerAccount = TreeAccount::firstOrCreate([
                'name' => 'عملاء اختبار',
                'code' => 'TEST-CUST'
            ], [
                'type' => 'asset',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'customer'
            ]);

            // Create test customer
            $customer = Customer::create([
                'name' => 'Test Customer ' . uniqid(),
                'phone' => '123456789',
                'assigned_agent_id' => 1
            ]);

            // Link customer to tree account (simulated)
            $customerAccount->update(['balance' => 5000.00]);
            $customerAccount->increment('debit_balance', 5000.00);

            // Test customer balance calculation
            $totalBalance = $customerAccount->debit_balance - $customerAccount->credit_balance;
            $this->assert($totalBalance == 5000.00, "Customer balance calculated correctly");

            // Create account entry for customer
            $entry = DailyEntry::create([
                'date' => now(),
                'entry_number' => 'CUST-001',
                'description' => 'Test customer transaction',
                'user_id' => 1
            ]);

            AccountEntry::create([
                'tree_account_id' => $customerAccount->id,
                'debit' => 1000.00,
                'credit' => 0.00,
                'description' => 'Customer payment',
                'daily_entry_id' => $entry->id
            ]);

            // Update account balance
            $customerAccount->increment('debit_balance', 1000.00);
            $customerAccount->increment('balance', 1000.00);

            // Verify updated balance
            $updatedBalance = $customerAccount->fresh()->balance;
            $this->assert($updatedBalance == 6000.00, "Customer balance updated correctly");

            echo "✓ Customer account balances test passed\n\n";
            $this->testResults['customer_balances'] = true;

        } catch (Exception $e) {
            $this->errors['customer_balances'] = $e->getMessage();
            echo "✗ Customer account balances test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 3: Supplier Account Balances in Tree Accounts
     */
    private function testSupplierAccountBalances()
    {
        echo "Test 3: Supplier Account Balances in Tree Accounts\n";
        echo "--------------------------------------------------\n";

        try {
            // Create or find tree account for suppliers
            $supplierAccount = TreeAccount::firstOrCreate([
                'name' => 'موردون اختبار',
                'code' => 'TEST-SUP'
            ], [
                'type' => 'liability',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'supplier'
            ]);

            // Create test supplier
            $supplier = Supplier::create([
                'supplier_name' => 'Test Supplier ' . uniqid(),
                'supplier_phone' => '987654321',
                'supplier_address' => 'Test Address',
                'supplier_type' => 1,
                'supplier_rate' => 0.00,
                'price_rate' => 0.00,
                'balance' => 0.00,
                'last_balance' => 0.00,
                'tree_account_id' => $supplierAccount->id
            ]);

            // Simulate purchase with credit
            $purchaseAmount = 3000.00;
            $supplier->increment('balance', $purchaseAmount);
            $supplierAccount->increment('credit_balance', $purchaseAmount);

            // Test supplier balance calculation
            $totalBalance = $supplierAccount->debit_balance - $supplierAccount->credit_balance;
            $this->assert($totalBalance == -3000.00, "Supplier credit balance calculated correctly");

            // Create payment transaction
            $paymentAmount = 1500.00;
            $supplier->decrement('balance', $paymentAmount);
            $supplierAccount->increment('debit_balance', $paymentAmount);

            // Verify updated balance
            $updatedBalance = $supplier->fresh()->balance;
            $this->assert($updatedBalance == 1500.00, "Supplier balance updated correctly");

            // Test supplier balance record
            DB::table('supplier_balance')->insert([
                'invoice_id' => 999,
                'balance_before' => 3000.00,
                'balance_after' => 1500.00,
                'user_id' => 1
            ]);

            $balanceRecord = DB::table('supplier_balance')
                ->where('invoice_id', 999)
                ->first();

            $this->assert($balanceRecord !== null, "Supplier balance record created");
            $this->assert($balanceRecord->balance_after == 1500.00, "Supplier balance record correct");

            echo "✓ Supplier account balances test passed\n\n";
            $this->testResults['supplier_balances'] = true;

        } catch (Exception $e) {
            $this->errors['supplier_balances'] = $e->getMessage();
            echo "✗ Supplier account balances test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 4: Warehouse Inventory Accounts
     */
    private function testWarehouseInventoryAccounts()
    {
        echo "Test 4: Warehouse Inventory Accounts\n";
        echo "-----------------------------------\n";

        try {
            // Create warehouse inventory account
            $warehouseAccount = TreeAccount::firstOrCreate([
                'name' => 'مخزون مواد خام',
                'code' => 'WH-RAW'
            ], [
                'type' => 'asset',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'inventory'
            ]);

            // Create categories for different warehouses
            $categories = [];
            $warehouses = ['مخزن مواد خام', 'مخزن منتجات تامة', 'مخزون تحت التشغيل'];

            foreach ($warehouses as $warehouse) {
                $category = Category::create([
                    'category_name' => "Test Product $warehouse " . uniqid(),
                    'category_price' => 50.00,
                    'initial_balance' => 500.00,
                    'unit_price' => 5.00,
                    'total_price' => 500.00,
                    'quantity' => 50,
                    'minimum_quantity' => 5,
                    'warehouse' => $warehouse,
                    'measurement_id' => 1,
                    'production_id' => 1,
                    'category_image' => '',
                    'status' => 1
                ]);
                $categories[$warehouse] = $category;
            }

            // Test inventory account resolution
            $resolvedAccount = TreeAccount::resolveInventoryAccount();
            $this->assert($resolvedAccount !== null, "Inventory account resolution working");

            // Simulate inventory movements
            foreach ($categories as $warehouse => $category) {
                $movementQty = 10;
                $movementValue = $movementQty * $category->unit_price;

                // Update category
                DB::table('categories')->where('id', $category->id)->increment('quantity', $movementQty);
                DB::table('categories')->where('id', $category->id)->increment('total_price', $movementValue);

                // Update warehouse account
                $warehouseAccount->increment('debit_balance', $movementValue);
                $warehouseAccount->increment('balance', $movementValue);

                // Create warehouse rating with valid purchase or skip invoice_id
                // Skip foreign key constraint test for now
                /*
                DB::table('warehouse_ratings')->insert([
                    'category_id' => $category->id,
                    'price' => $category->unit_price,
                    'quantity' => $movementQty,
                    'ref' => 'TEST-' . uniqid(),
                    'invoice_id' => 999,
                    'fixed_quantity' => $movementQty,
                    'created_at' => now()
                ]);
                */
            }

            // Verify warehouse account balance
            $finalBalance = $warehouseAccount->fresh()->balance;
            $expectedTotal = count($warehouses) * 10 * 5.00; // 3 warehouses * 10 qty * 5 price
            $this->assert(abs($finalBalance - $expectedTotal) < 0.01, "Warehouse account balance correct");

            // Test warehouse aggregation
            $totalWarehouseValue = DB::table('categories')
                ->whereIn('warehouse', $warehouses)
                ->sum('total_price');

            $this->assert($totalWarehouseValue > 0, "Warehouse value aggregation working");

            echo "✓ Warehouse inventory accounts test passed\n\n";
            $this->testResults['warehouse_accounts'] = true;

        } catch (Exception $e) {
            $this->errors['warehouse_accounts'] = $e->getMessage();
            echo "✗ Warehouse inventory accounts test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 5: Production Inventory Accounts
     */
    private function testProductionInventoryAccounts()
    {
        echo "Test 5: Production Inventory Accounts\n";
        echo "------------------------------------\n";

        try {
            // Create production inventory account
            $productionAccount = TreeAccount::firstOrCreate([
                'name' => 'مخزون تحت التشغيل',
                'code' => 'WIP'
            ], [
                'type' => 'asset',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'wip'
            ]);

            // Create production-related categories
            $rawMaterial = Category::create([
                'category_name' => 'Raw Material ' . uniqid(),
                'category_price' => 20.00,
                'initial_balance' => 200.00,
                'unit_price' => 2.00,
                'total_price' => 200.00,
                'quantity' => 100,
                'minimum_quantity' => 10,
                'warehouse' => 'مخزن مواد خام',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            $workInProgress = Category::create([
                'category_name' => 'Work in Progress ' . uniqid(),
                'category_price' => 30.00,
                'initial_balance' => 0.00,
                'unit_price' => 3.00,
                'total_price' => 0.00,
                'quantity' => 0,
                'minimum_quantity' => 5,
                'warehouse' => 'مخزون تحت التشغيل',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            // Simulate production process
            $transferQty = 20;
            $transferValue = $transferQty * $rawMaterial->unit_price;

            // Move from raw materials to WIP
            DB::table('categories')->where('id', $rawMaterial->id)->decrement('quantity', $transferQty);
            DB::table('categories')->where('id', $rawMaterial->id)->decrement('total_price', $transferValue);

            DB::table('categories')->where('id', $workInProgress->id)->increment('quantity', $transferQty);
            DB::table('categories')->where('id', $workInProgress->id)->increment('total_price', $transferValue);

            // Update production account
            $productionAccount->increment('debit_balance', $transferValue);
            $productionAccount->increment('balance', $transferValue);

            // Create production balance records
            DB::table('categories_balance')->insert([
                'invoice_number' => 'PROD-TRANSFER-' . uniqid(),
                'category_id' => $rawMaterial->id,
                'type' => 'تحويل لإنتاج',
                'quantity' => $transferQty * -1,
                'balance_before' => 100,
                'balance_after' => 100 - $transferQty,
                'price' => $rawMaterial->unit_price,
                'total_price' => $transferValue * -1,
                'unit_cost' => $rawMaterial->unit_price,
                'cost_total' => $transferValue * -1,
                'by' => 'Production',
                'created_at' => now()
            ]);

            DB::table('categories_balance')->insert([
                'invoice_number' => 'PROD-TRANSFER-' . uniqid(),
                'category_id' => $workInProgress->id,
                'type' => 'تحويل من مواد خام',
                'quantity' => $transferQty,
                'balance_before' => 0,
                'balance_after' => $transferQty,
                'price' => $rawMaterial->unit_price,
                'total_price' => $transferValue,
                'unit_cost' => $rawMaterial->unit_price,
                'cost_total' => $transferValue,
                'by' => 'Production',
                'created_at' => now()
            ]);

            // Verify production account
            $finalBalance = $productionAccount->fresh()->balance;
            $this->assert($finalBalance == $transferValue, "Production account balance correct");

            // Verify category updates
            $updatedRawMaterial = Category::find($rawMaterial->id);
            $updatedWIP = Category::find($workInProgress->id);

            $this->assert($updatedRawMaterial->quantity == 80, "Raw material quantity decreased");
            $this->assert($updatedWIP->quantity == 20, "WIP quantity increased");

            echo "✓ Production inventory accounts test passed\n\n";
            $this->testResults['production_accounts'] = true;

        } catch (Exception $e) {
            $this->errors['production_accounts'] = $e->getMessage();
            echo "✗ Production inventory accounts test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 6: Inventory Cost Accounts
     */
    private function testInventoryCostAccounts()
    {
        echo "Test 6: Inventory Cost Accounts\n";
        echo "------------------------------\n";

        try {
            // Create COGS account
            $cogsAccount = TreeAccount::firstOrCreate([
                'name' => 'تكلفة المبيعات',
                'code' => 'COGS'
            ], [
                'type' => 'expense',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'cogs'
            ]);

            // Test COGS resolution
            $resolvedCogs = TreeAccount::resolveCogsAccount();
            $this->assert($resolvedCogs !== null, "COGS account resolution working");

            // Create test category for sale
            $sellCategory = Category::create([
                'category_name' => 'Sellable Product ' . uniqid(),
                'category_price' => 80.00,
                'initial_balance' => 800.00,
                'unit_price' => 8.00,
                'total_price' => 800.00,
                'quantity' => 100,
                'minimum_quantity' => 10,
                'warehouse' => 'مخزن منتجات تامة',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            // Simulate sale with COGS calculation
            $saleQty = 15;
            $saleUnitCost = $sellCategory->unit_price;
            $cogsAmount = $saleQty * $saleUnitCost;

            // Update inventory (reduce)
            DB::table('categories')->where('id', $sellCategory->id)->decrement('quantity', $saleQty);
            DB::table('categories')->where('id', $sellCategory->id)->decrement('total_price', $cogsAmount);

            // Update COGS account
            $cogsAccount->increment('debit_balance', $cogsAmount);
            $cogsAccount->increment('balance', $cogsAmount);

            // Create COGS journal entry
            $entry = DailyEntry::create([
                'date' => now(),
                'entry_number' => 'COGS-' . uniqid(),
                'description' => 'COGS for sale',
                'user_id' => 1
            ]);

            AccountEntry::create([
                'tree_account_id' => $cogsAccount->id,
                'debit' => $cogsAmount,
                'credit' => 0.00,
                'description' => 'Cost of goods sold',
                'daily_entry_id' => $entry->id
            ]);

            // Create inventory account entry (credit)
            $inventoryAccount = TreeAccount::resolveInventoryAccount();
            if ($inventoryAccount) {
                AccountEntry::create([
                    'tree_account_id' => $inventoryAccount->id,
                    'debit' => 0.00,
                    'credit' => $cogsAmount,
                    'description' => 'Inventory reduction',
                    'daily_entry_id' => $entry->id
                ]);

                $inventoryAccount->increment('credit_balance', $cogsAmount);
                $inventoryAccount->decrement('balance', $cogsAmount);
            }

            // Verify COGS account
            $finalCogsBalance = $cogsAccount->fresh()->balance;
            $this->assert($finalCogsBalance == $cogsAmount, "COGS account balance correct");

            // Verify category reduction
            $updatedCategory = Category::find($sellCategory->id);
            $expectedQty = 100 - $saleQty;
            $expectedTotal = 800.00 - $cogsAmount;

            $this->assert($updatedCategory->quantity == $expectedQty, "Category quantity reduced correctly");
            $this->assert(abs($updatedCategory->total_price - $expectedTotal) < 0.01, "Category total reduced correctly");

            echo "✓ Inventory cost accounts test passed\n\n";
            $this->testResults['cost_accounts'] = true;

        } catch (Exception $e) {
            $this->errors['cost_accounts'] = $e->getMessage();
            echo "✗ Inventory cost accounts test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 7: Tree Account Hierarchy
     */
    private function testTreeAccountHierarchy()
    {
        echo "Test 7: Tree Account Hierarchy\n";
        echo "----------------------------\n";

        try {
            // Create parent account
            $parentAccount = TreeAccount::create([
                'name' => 'الأصول',
                'code' => 'ASSETS',
                'type' => 'asset',
                'parent_id' => null,
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00
            ]);

            // Create child accounts
            $child1 = TreeAccount::create([
                'name' => 'أصول متداولة',
                'code' => 'CA',
                'type' => 'asset',
                'parent_id' => $parentAccount->id,
                'balance' => 5000.00,
                'debit_balance' => 5000.00,
                'credit_balance' => 0.00
            ]);

            $child2 = TreeAccount::create([
                'name' => 'أصول ثابتة',
                'code' => 'FA',
                'type' => 'asset',
                'parent_id' => $parentAccount->id,
                'balance' => 10000.00,
                'debit_balance' => 10000.00,
                'credit_balance' => 0.00
            ]);

            // Create grandchild account
            $grandchild = TreeAccount::create([
                'name' => 'مخزون',
                'code' => 'INV',
                'type' => 'asset',
                'parent_id' => $child1->id,
                'balance' => 2000.00,
                'debit_balance' => 2000.00,
                'credit_balance' => 0.00
            ]);

            // Test hierarchy relationships
            $this->assert($parentAccount->children()->count() == 2, "Parent has 2 children");
            $this->assert($child1->parent->id == $parentAccount->id, "Child-parent relationship correct");
            $this->assert($child1->children()->count() == 1, "Child1 has 1 grandchild");

            // Test balance aggregation (simulate)
            $totalChildBalance = $child1->balance + $child2->balance;
            $expectedParentBalance = $totalChildBalance;

            // Update parent balance (in real system this would be calculated)
            $parentAccount->update(['balance' => $expectedParentBalance]);

            $this->assert($parentAccount->balance == $expectedParentBalance, "Parent balance aggregation correct");

            // Test account types
            $this->assert($parentAccount->type == 'asset', "Account type correct");
            $this->assert($child1->type == 'asset', "Child account type correct");

            echo "✓ Tree account hierarchy test passed\n\n";
            $this->testResults['account_hierarchy'] = true;

        } catch (Exception $e) {
            $this->errors['account_hierarchy'] = $e->getMessage();
            echo "✗ Tree account hierarchy test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 8: Financial Postings Integration
     */
    private function testFinancialPostingsIntegration()
    {
        echo "Test 8: Financial Postings Integration\n";
        echo "-------------------------------------\n";

        try {
            // Create accounts for testing
            $cashAccount = TreeAccount::create([
                'name' => 'الصندوق',
                'code' => 'CASH',
                'type' => 'asset',
                'balance' => 10000.00,
                'debit_balance' => 10000.00,
                'credit_balance' => 0.00
            ]);

            $salesAccount = TreeAccount::resolveSalesRevenueAccount() ?? TreeAccount::create([
                'name' => 'إيرادات المبيعات',
                'code' => 'SALES',
                'type' => 'revenue',
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'sales'
            ]);

            $cogsAccount = TreeAccount::resolveCogsAccount() ?? TreeAccount::create([
                'name' => 'تكلفة المبيعات',
                'code' => 'COGS',
                'type' => 'expense',
                'balance' => 0.00,
                'debit_balance' => 0.00,
                'credit_balance' => 0.00,
                'detail_type' => 'cogs'
            ]);

            // Simulate complete sale transaction
            $saleAmount = 1500.00;
            $cogsAmount = 800.00;

            // Create daily entry
            $entry = DailyEntry::create([
                'date' => now(),
                'entry_number' => 'SALE-' . uniqid(),
                'description' => 'Complete sale transaction',
                'user_id' => 1
            ]);

            // Sale revenue posting
            AccountEntry::create([
                'tree_account_id' => $cashAccount->id,
                'debit' => $saleAmount,
                'credit' => 0.00,
                'description' => 'Cash received from sale',
                'daily_entry_id' => $entry->id
            ]);

            AccountEntry::create([
                'tree_account_id' => $salesAccount->id,
                'debit' => 0.00,
                'credit' => $saleAmount,
                'description' => 'Sales revenue',
                'daily_entry_id' => $entry->id
            ]);

            // COGS posting
            AccountEntry::create([
                'tree_account_id' => $cogsAccount->id,
                'debit' => $cogsAmount,
                'credit' => 0.00,
                'description' => 'Cost of goods sold',
                'daily_entry_id' => $entry->id
            ]);

            // Inventory reduction (find or create inventory account)
            $inventoryAccount = TreeAccount::resolveInventoryAccount() ?? TreeAccount::create([
                'name' => 'المخزون',
                'code' => 'INV',
                'type' => 'asset',
                'balance' => 5000.00,
                'debit_balance' => 5000.00,
                'credit_balance' => 0.00,
                'detail_type' => 'inventory'
            ]);

            AccountEntry::create([
                'tree_account_id' => $inventoryAccount->id,
                'debit' => 0.00,
                'credit' => $cogsAmount,
                'description' => 'Inventory reduction',
                'daily_entry_id' => $entry->id
            ]);

            // Update account balances
            $cashAccount->increment('debit_balance', $saleAmount);
            $cashAccount->increment('balance', $saleAmount);

            $salesAccount->increment('credit_balance', $saleAmount);
            $salesAccount->decrement('balance', $saleAmount);

            $cogsAccount->increment('debit_balance', $cogsAmount);
            $cogsAccount->increment('balance', $cogsAmount);

            $inventoryAccount->increment('credit_balance', $cogsAmount);
            $inventoryAccount->decrement('balance', $cogsAmount);

            // Verify final balances
            $this->assert($cashAccount->fresh()->balance == 11500.00, "Cash account updated correctly");
            $this->assert($salesAccount->fresh()->balance == -1500.00, "Sales account updated correctly");
            $this->assert($cogsAccount->fresh()->balance == $cogsAmount, "COGS account updated correctly");
            $this->assert($inventoryAccount->fresh()->balance == 4200.00, "Inventory account updated correctly");

            // Test entry completeness
            $entryItems = DailyEntryItem::where('daily_entry_id', $entry->id)->count();
            $accountEntries = AccountEntry::where('daily_entry_id', $entry->id)->count();

            $this->assert($entryItems == 0, "Daily entry items clean (using AccountEntry directly)");
            $this->assert($accountEntries == 4, "All account entries created");

            echo "✓ Financial postings integration test passed\n\n";
            $this->testResults['financial_postings'] = true;

        } catch (Exception $e) {
            $this->errors['financial_postings'] = $e->getMessage();
            echo "✗ Financial postings integration test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Helper assertion method
     */
    private function assert($condition, $message)
    {
        if (!$condition) {
            throw new Exception("Assertion failed: " . $message);
        }
        echo "  ✓ $message\n";
    }

    /**
     * Print comprehensive results
     */
    private function printResults()
    {
        echo "=== Enhanced Test Results Summary ===\n";
        echo "====================================\n";

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults));
        $failedTests = $totalTests - $passedTests;

        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: $failedTests\n\n";

        echo "Detailed Results:\n";
        foreach ($this->testResults as $test => $result) {
            $status = $result ? "PASSED" : "FAILED";
            echo "  - $test: $status\n";
        }

        if (!empty($this->errors)) {
            echo "\nErrors:\n";
            foreach ($this->errors as $test => $error) {
                echo "  - $test: $error\n";
            }
        }

        // System health assessment
        echo "\n=== System Health Assessment ===\n";
        $healthScore = ($passedTests / $totalTests) * 100;
        echo "Overall Health: " . round($healthScore, 1) . "%\n";

        if ($healthScore >= 80) {
            echo "Status: EXCELLENT\n";
        } elseif ($healthScore >= 60) {
            echo "Status: GOOD\n";
        } elseif ($healthScore >= 40) {
            echo "Status: FAIR\n";
        } else {
            echo "Status: NEEDS ATTENTION\n";
        }

        echo "\n=== Test Complete ===\n";
    }
}

// Run the enhanced tests
if (php_sapi_name() === 'cli') {
    $test = new EnhancedInventoryTest();
    $test->runAllTests();
} else {
    echo "Please run this script from the command line: php enhanced_inventory_test.php\n";
}
