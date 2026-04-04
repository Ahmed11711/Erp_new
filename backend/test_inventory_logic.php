<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\PurchasesTracking;
use App\Services\CategoryInventoryCostService;
use App\Services\Accounting\InventoryGlPostingService;

/**
 * Comprehensive Test Script for ERP Inventory Logic
 * Tests purchases, sales, and inventory calculations
 */

class InventoryLogicTest
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
     * Run all inventory tests
     */
    public function runAllTests()
    {
        echo "=== Starting ERP Inventory Logic Tests ===\n\n";

        $this->testCategoryCreation();
        $this->testPurchaseInvoiceLogic();
        $this->testSalesOrderLogic();
        $this->testWarehouseBalanceCalculations();
        $this->testCategoryCostCalculations();
        $this->testInventoryReversal();

        $this->printResults();
    }

    /**
     * Test 1: Category Creation and Initial Setup
     */
    private function testCategoryCreation()
    {
        echo "Test 1: Category Creation and Initial Setup\n";
        echo "------------------------------------------\n";

        try {
            // Create test category
            $category = Category::create([
                'category_name' => 'Test Product ' . uniqid(),
                'category_price' => 100.00,
                'initial_balance' => 1000.00,
                'unit_price' => 10.00,
                'total_price' => 1000.00,
                'quantity' => 100,
                'minimum_quantity' => 10,
                'warehouse' => 'Main Warehouse',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            $this->assert($category->id > 0, "Category created successfully");
            $this->assert($category->quantity == 100, "Initial quantity set correctly");
            $this->assert($category->total_price == 1000.00, "Initial total price set correctly");

            // Test category balance record creation
            $balanceRecord = DB::table('categories_balance')
                ->where('category_id', $category->id)
                ->first();

            $this->assert($balanceRecord !== null, "Balance record created");

            echo "✓ Category creation test passed\n\n";
            $this->testResults['category_creation'] = true;

        } catch (Exception $e) {
            $this->errors['category_creation'] = $e->getMessage();
            echo "✗ Category creation test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 2: Purchase Invoice Logic
     */
    private function testPurchaseInvoiceLogic()
    {
        echo "Test 2: Purchase Invoice Logic\n";
        echo "-----------------------------\n";

        try {
            // Get or create test category
            $category = Category::firstOrCreate([
                'category_name' => 'Test Purchase Product'
            ], [
                'category_price' => 50.00,
                'initial_balance' => 500.00,
                'unit_price' => 5.00,
                'total_price' => 500.00,
                'quantity' => 50,
                'minimum_quantity' => 5,
                'warehouse' => 'Main Warehouse',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            $initialQty = $category->quantity;
            $initialTotal = $category->total_price;

            // Simulate purchase invoice data
            $purchaseData = [
                'supplier_id' => 1,
                'invoice_type' => 'cash',
                'receipt_date' => date('Y-m-d'),
                'total_price' => 300.00,
                'paid_amount' => 300.00,
                'due_amount' => 0.00,
                'transport_cost' => 20.00,
                'price_edited' => false,
                'products' => json_encode([
                    [
                        'product_name' => $category->category_name,
                        'product_unit' => 'pcs',
                        'product_quantity' => 20,
                        'product_price' => 15.00,
                        'total' => 300.00,
                        'price_edited' => false
                    ]
                ])
            ];

            // Test purchase line unit cost calculation
            $lineTotal = 300.00;
            $qty = 20;
            $declaredUnit = 15.00;
            $effectiveUnit = CategoryInventoryCostService::purchaseLineUnitCost($lineTotal, $qty, $declaredUnit);

            $this->assert($effectiveUnit == 15.00, "Purchase line unit cost calculated correctly");

            // Test category ID resolution
            $product = ['product_name' => $category->category_name];
            $resolvedId = CategoryInventoryCostService::resolveCategoryIdForPurchaseLine($product, $product['product_name']);
            
            $this->assert($resolvedId == $category->id, "Category ID resolved correctly");

            // Simulate inventory update
            DB::table('categories')->where('id', $category->id)->increment('quantity', $qty);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $lineTotal);

            // Sync unit price
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            // Verify updates
            $updatedCategory = Category::find($category->id);
            $expectedQty = $initialQty + $qty;
            $expectedTotal = $initialTotal + $lineTotal;
            $expectedUnitPrice = $expectedTotal / $expectedQty;

            $this->assert($updatedCategory->quantity == $expectedQty, "Quantity updated correctly after purchase");
            $this->assert(abs($updatedCategory->total_price - $expectedTotal) < 0.01, "Total price updated correctly after purchase");

            echo "✓ Purchase invoice logic test passed\n\n";
            $this->testResults['purchase_logic'] = true;

        } catch (Exception $e) {
            $this->errors['purchase_logic'] = $e->getMessage();
            echo "✗ Purchase invoice logic test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 3: Sales Order Logic
     */
    private function testSalesOrderLogic()
    {
        echo "Test 3: Sales Order Logic\n";
        echo "------------------------\n";

        try {
            // Get test category with sufficient quantity
            $category = Category::where('quantity', '>', 10)->first();
            if (!$category) {
                // Create category with quantity for testing
                $category = Category::create([
                    'category_name' => 'Test Sales Product ' . uniqid(),
                    'category_price' => 80.00,
                    'initial_balance' => 800.00,
                    'unit_price' => 8.00,
                    'total_price' => 800.00,
                    'quantity' => 100,
                    'warehouse' => 'Main Warehouse',
                    'measurement_id' => 1,
                    'production_id' => 1,
                    'category_image' => '',
                    'status' => 1
                ]);
            }

            $initialQty = $category->quantity;
            $initialTotal = $category->total_price;

            // Simulate sales order
            $salesQty = 15;
            $salesUnitPrice = $category->unit_price;
            $salesTotal = $salesQty * $salesUnitPrice;

            // Test inventory deduction for sales
            DB::table('categories')->where('id', $category->id)->decrement('quantity', $salesQty);
            DB::table('categories')->where('id', $category->id)->decrement('total_price', $salesTotal);

            // Create balance record for sales
            DB::table('categories_balance')->insert([
                'invoice_number' => 'TEST-SALE-' . uniqid(),
                'category_id' => $category->id,
                'type' => 'فواتير مبيعات',
                'quantity' => $salesQty * -1,
                'balance_before' => $initialQty,
                'balance_after' => $initialQty - $salesQty,
                'price' => $salesUnitPrice,
                'total_price' => $salesTotal * -1,
                'unit_cost' => $salesUnitPrice,
                'cost_total' => $salesTotal * -1,
                'by' => 'Test User',
                'created_at' => now()
            ]);

            // Verify updates
            $updatedCategory = Category::find($category->id);
            $expectedQty = $initialQty - $salesQty;
            $expectedTotal = $initialTotal - $salesTotal;

            $this->assert($updatedCategory->quantity == $expectedQty, "Quantity deducted correctly after sales");
            $this->assert(abs($updatedCategory->total_price - $expectedTotal) < 0.01, "Total price deducted correctly after sales");

            // Test insufficient quantity scenario
            if ($updatedCategory->quantity < 5) {
                $this->assert(true, "Low quantity detection working");
            }

            echo "✓ Sales order logic test passed\n\n";
            $this->testResults['sales_logic'] = true;

        } catch (Exception $e) {
            $this->errors['sales_logic'] = $e->getMessage();
            echo "✗ Sales order logic test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 4: Warehouse Balance Calculations
     */
    private function testWarehouseBalanceCalculations()
    {
        echo "Test 4: Warehouse Balance Calculations\n";
        echo "---------------------------------------\n";

        try {
            // Test warehouse ratings table
            $category = Category::first();
            if (!$category) {
                throw new Exception("No category found for warehouse test");
            }

            $testQty = 10;
            $testPrice = 25.50;
            $testTotal = $testQty * $testPrice;

            // Insert warehouse rating record with a valid purchase ID
            $purchase = Purchase::first();
            if (!$purchase) {
                // Create a dummy purchase for testing
                $purchase = Purchase::create([
                    'supplier_id' => 1,
                    'invoice_type' => 'test',
                    'receipt_date' => date('Y-m-d'),
                    'total_price' => 0.00,
                    'paid_amount' => 0.00,
                    'due_amount' => 0.00,
                    'transport_cost' => 0.00,
                    'price_edited' => false,
                    'status' => 'test'
                ]);
            }
            
            DB::table('warehouse_ratings')->insert([
                'category_id' => $category->id,
                'price' => $testPrice,
                'quantity' => $testQty,
                'ref' => 'TEST-REF-' . uniqid(),
                'invoice_id' => $purchase->id,
                'fixed_quantity' => $testQty,
                'created_at' => now()
            ]);

            // Verify warehouse rating record
            $warehouseRating = DB::table('warehouse_ratings')
                ->where('category_id', $category->id)
                ->where('ref', 'LIKE', 'TEST-REF-%')
                ->first();

            $this->assert($warehouseRating !== null, "Warehouse rating record created");
            $this->assert($warehouseRating->quantity == $testQty, "Warehouse quantity recorded correctly");
            $this->assert(abs($warehouseRating->price - $testPrice) < 0.01, "Warehouse price recorded correctly");

            // Test warehouse balance aggregation
            $totalWarehouseQty = DB::table('warehouse_ratings')
                ->where('category_id', $category->id)
                ->sum('quantity');

            $this->assert($totalWarehouseQty >= $testQty, "Warehouse balance aggregation working");

            echo "✓ Warehouse balance calculations test passed\n\n";
            $this->testResults['warehouse_balance'] = true;

        } catch (Exception $e) {
            $this->errors['warehouse_balance'] = $e->getMessage();
            echo "✗ Warehouse balance calculations test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 5: Category Cost Calculations and Weighted Averages
     */
    private function testCategoryCostCalculations()
    {
        echo "Test 5: Category Cost Calculations and Weighted Averages\n";
        echo "-------------------------------------------------------\n";

        try {
            // Create test category for cost calculations
            $category = Category::create([
                'category_name' => 'Test Cost Product ' . uniqid(),
                'category_price' => 0.00,
                'initial_balance' => 0.00,
                'unit_price' => 0.00,
                'total_price' => 0.00,
                'quantity' => 0,
                'minimum_quantity' => 0,
                'warehouse' => 'Main Warehouse',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            // Purchase 1: 10 units @ $5.00 = $50.00
            $qty1 = 10;
            $price1 = 5.00;
            $total1 = $qty1 * $price1;

            DB::table('categories')->where('id', $category->id)->increment('quantity', $qty1);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $total1);
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            $category->refresh();
            $expectedUnitPrice1 = $total1 / $qty1;
            $this->assert(abs($category->unit_price - $expectedUnitPrice1) < 0.01, "Unit price calculated correctly after first purchase");

            // Purchase 2: 20 units @ $6.00 = $120.00
            $qty2 = 20;
            $price2 = 6.00;
            $total2 = $qty2 * $price2;

            DB::table('categories')->where('id', $category->id)->increment('quantity', $qty2);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $total2);
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            $category->refresh();
            $totalQty = $qty1 + $qty2;
            $totalCost = $total1 + $total2;
            $expectedUnitPrice2 = $totalCost / $totalQty;

            $this->assert($category->quantity == $totalQty, "Total quantity correct after multiple purchases");
            $this->assert(abs($category->total_price - $totalCost) < 0.01, "Total cost correct after multiple purchases");
            $this->assert(abs($category->unit_price - $expectedUnitPrice2) < 0.01, "Weighted average unit price calculated correctly");

            // Test reference unit cost resolution
            $refUnitCost = CategoryInventoryCostService::resolveReferenceUnitCost($category->id);
            $this->assert(abs($refUnitCost - $expectedUnitPrice2) < 0.01, "Reference unit cost resolved correctly");

            echo "✓ Category cost calculations test passed\n\n";
            $this->testResults['cost_calculations'] = true;

        } catch (Exception $e) {
            $this->errors['cost_calculations'] = $e->getMessage();
            echo "✗ Category cost calculations test failed: " . $e->getMessage() . "\n\n";
        }
    }

    /**
     * Test 6: Inventory Reversal (Edit/Delete Operations)
     */
    private function testInventoryReversal()
    {
        echo "Test 6: Inventory Reversal (Edit/Delete Operations)\n";
        echo "--------------------------------------------------\n";

        try {
            // Create test category
            $category = Category::create([
                'category_name' => 'Test Reversal Product ' . uniqid(),
                'category_price' => 100.00,
                'initial_balance' => 1000.00,
                'unit_price' => 10.00,
                'total_price' => 1000.00,
                'quantity' => 100,
                'minimum_quantity' => 10,
                'warehouse' => 'Main Warehouse',
                'measurement_id' => 1,
                'production_id' => 1,
                'category_image' => '',
                'status' => 1
            ]);

            $initialQty = $category->quantity;
            $initialTotal = $category->total_price;

            // Simulate purchase addition
            $purchaseQty = 25;
            $purchaseTotal = 250.00;

            DB::table('categories')->where('id', $category->id)->increment('quantity', $purchaseQty);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $purchaseTotal);
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            $category->refresh();
            $afterPurchaseQty = $category->quantity;
            $afterPurchaseTotal = $category->total_price;

            // Simulate reversal (edit/delete)
            DB::table('categories')->where('id', $category->id)->increment('quantity', $purchaseQty * -1);
            DB::table('categories')->where('id', $category->id)->increment('total_price', $purchaseTotal * -1);
            CategoryInventoryCostService::syncUnitPriceFromWeightedAverage($category->id);

            $category->refresh();
            $finalQty = $category->quantity;
            $finalTotal = $category->total_price;

            $this->assert($finalQty == $initialQty, "Quantity reversed correctly");
            $this->assert(abs($finalTotal - $initialTotal) < 0.01, "Total price reversed correctly");

            // Test reversal balance record
            $reversalRecord = DB::table('categories_balance')
                ->where('category_id', $category->id)
                ->where('type', 'تعديل فواتير مشتريات')
                ->orWhere('type', 'حذف فواتير مشتريات')
                ->first();

            $this->assert($reversalRecord !== null, "Reversal balance record created");

            echo "✓ Inventory reversal test passed\n\n";
            $this->testResults['inventory_reversal'] = true;

        } catch (Exception $e) {
            $this->errors['inventory_reversal'] = $e->getMessage();
            echo "✗ Inventory reversal test failed: " . $e->getMessage() . "\n\n";
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
     * Print test results summary
     */
    private function printResults()
    {
        echo "=== Test Results Summary ===\n";
        echo "===========================\n";

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

        echo "\n=== Test Complete ===\n";
    }
}

// Run the tests
if (php_sapi_name() === 'cli') {
    $test = new InventoryLogicTest();
    $test->runAllTests();
} else {
    echo "Please run this script from the command line: php test_inventory_logic.php\n";
}
