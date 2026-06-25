<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\DailyEntryItem;
use App\Models\DocumentSequence;
use App\Models\ShippingCompany;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\TransactionType;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Processing\ProcessingAccountingService;
use App\Services\Processing\ProcessingDispatchService;
use App\Services\Processing\ProcessingWarehouseResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessingDispatchAccountingTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private User $user;

    private TreeAccount $rawInventoryAcc;

    private TreeAccount $transitInventoryAcc;

    private TreeAccount $serviceExpenseAcc;

    private TreeAccount $supplierAcc;

    private Stock $rawStock;

    private Supplier $supplier;

    private Category $category;

    private ShippingCompany $representative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codePrefix = 'PD' . substr(str_replace('.', '', uniqid('', true)), -8);
        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);
        $this->actingAs($this->user, 'api');

        $this->seedChart();
        $this->seedProcessingTransactionTypes();
        $this->seedOperationalData();
    }

    public function test_ensure_materials_stock_syncs_subcontract_account_link(): void
    {
        $brokenStock = Stock::query()
            ->where('warehouse_type', 'materials_at_vendor')
            ->where('asset_id', '!=', app(ProcessingAccountingService::class)->resolveMaterialsAtVendorAccount()->id)
            ->first();

        if (! $brokenStock) {
            $brokenStock = Stock::query()->create([
                'name' => 'مواد لدى مندوب ' . $this->codePrefix,
                'name_en' => 'Transit test',
                'balance' => 0,
                'asset_id' => $this->rawInventoryAcc->id,
                'active' => true,
                'warehouse_type' => 'materials_at_vendor',
            ]);
        }

        $stock = ProcessingWarehouseResolver::ensureMaterialsAtVendorStock();

        $this->assertSame('materials_at_vendor', $stock->warehouse_type);
        $this->assertGreaterThan(0, (int) $stock->asset_id);

        $resolved = TreeAccount::resolveInventoryAccountForStock($stock->fresh());
        $this->assertNotNull($resolved);
        $this->assertSame('inventory_subcontract', $resolved->detail_type);
        $this->assertNotSame($this->rawInventoryAcc->id, $resolved->id);
    }

    public function test_dispatch_reclassification_is_balanced_and_preserves_total_inventory(): void
    {
        Stock::query()->create([
            'name' => 'مواد لدى مندوب ' . $this->codePrefix,
            'name_en' => 'Transit test',
            'balance' => 0,
            'asset_id' => $this->transitInventoryAcc->id,
            'active' => true,
            'warehouse_type' => 'materials_at_vendor',
        ]);

        $rawBefore = $this->glNet(app(ProcessingAccountingService::class)->resolveStockAccount((int) $this->rawStock->id)->id);
        $transitAcc = app(ProcessingAccountingService::class)->resolveMaterialsAtVendorAccount();
        $transitBefore = $this->glNet($transitAcc->id);
        $amount = 250.0;

        app(ProcessingAccountingService::class)->postDispatchReclassification(
            $amount,
            (int) $this->rawStock->id,
            'MDN-TEST',
            'SUB-DSP-TEST-' . $this->codePrefix
        );

        $rawAccId = app(ProcessingAccountingService::class)->resolveStockAccount((int) $this->rawStock->id)->id;
        $this->assertEqualsWithDelta($rawBefore - $amount, $this->glNet($rawAccId), 0.02);
        $this->assertEqualsWithDelta($transitBefore + $amount, $this->glNet($transitAcc->id), 0.02);
    }

    public function test_submit_voucher_moves_stock_and_posts_service_expense_with_supplier_payable(): void
    {
        Stock::query()->create([
            'name' => 'مواد لدى مندوب ' . $this->codePrefix,
            'name_en' => 'Transit test',
            'balance' => 0,
            'asset_id' => $this->transitInventoryAcc->id,
            'active' => true,
            'warehouse_type' => 'materials_at_vendor',
        ]);

        $serviceAmount = 100.0;
        $dispatchQty = min(2.0, max(1.0, (float) $this->category->quantity - 1));
        $supplierBefore = (float) $this->supplier->balance;
        $rawQtyBefore = (float) $this->category->quantity;
        $rawInventoryAcc = app(ProcessingAccountingService::class)->resolveStockAccount((int) $this->rawStock->id);
        $rawGlBefore = $this->glNet($rawInventoryAcc->id);
        $transitAcc = app(ProcessingAccountingService::class)->resolveMaterialsAtVendorAccount();
        $transitGlBefore = $this->glNet($transitAcc->id);
        $serviceAcc = app(ProcessingAccountingService::class)->resolveServiceExpenseAccount();
        $supplierAcc = app(ProcessingAccountingService::class)->ensureSupplierAccount($this->supplier);
        $expenseGlBefore = $this->glNet($serviceAcc->id);
        $supplierGlBefore = $this->glNet($supplierAcc->id);

        $result = app(ProcessingDispatchService::class)->submitVoucher([
            'supplier_id' => $this->supplier->id,
            'source_stock_id' => $this->rawStock->id,
            'dispatch_date' => now()->toDateString(),
            'dispatch_type' => 'goods_to_supplier',
            'representative_type' => 'internal',
            'shipping_company_id' => $this->representative->id,
            'lines' => [[
                'category_id' => $this->category->id,
                'ordered_qty' => $dispatchQty,
                'expected_service_amount' => $serviceAmount,
            ]],
        ]);

        $this->category->refresh();
        $this->supplier->refresh();

        $this->assertSame('posted', $result['dispatch']->status);
        $this->assertNotNull($result['invoice']);
        $this->assertEqualsWithDelta($rawQtyBefore - $dispatchQty, (float) $this->category->quantity, 0.001);
        $this->assertEqualsWithDelta($supplierBefore + $serviceAmount, (float) $this->supplier->balance, 0.02);

        $materialMoved = $rawGlBefore - $this->glNet($rawInventoryAcc->id);
        $this->assertGreaterThan(0, $materialMoved);
        $this->assertEqualsWithDelta($materialMoved, $this->glNet($transitAcc->id) - $transitGlBefore, 0.02);
        $this->assertEqualsWithDelta(0.0, ($rawGlBefore - $this->glNet($rawInventoryAcc->id)) - ($this->glNet($transitAcc->id) - $transitGlBefore), 0.02);

        $this->assertEqualsWithDelta($expenseGlBefore + $serviceAmount, $this->glNet($serviceAcc->id), 0.02);
        $this->assertEqualsWithDelta($supplierGlBefore - $serviceAmount, $this->glNet($supplierAcc->id), 0.02);
    }

    private function assertGlLine(int $accountId, float $expectedDebit, float $expectedCredit): void
    {
        $debit = (float) DailyEntryItem::query()->where('account_id', $accountId)->sum('debit');
        $credit = (float) DailyEntryItem::query()->where('account_id', $accountId)->sum('credit');

        $this->assertEqualsWithDelta($expectedDebit, $debit, 0.02, "Debit mismatch for account {$accountId}");
        $this->assertEqualsWithDelta($expectedCredit, $credit, 0.02, "Credit mismatch for account {$accountId}");
    }

    private function glNet(int $accountId): float
    {
        return (float) DailyEntryItem::query()
            ->where('account_id', $accountId)
            ->sum(DB::raw('debit - credit'));
    }

    private function seedChart(): void
    {
        $p = $this->codePrefix;

        $assetRoot = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'أصول اختبار تشغيل',
            'type' => 'asset',
            'level' => 1,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->rawInventoryAcc = TreeAccount::create([
            'code' => $p . '1000223',
            'name' => 'مخزون مواد خام اختبار',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $assetRoot->id,
            'detail_type' => 'inventory_raw',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->transitInventoryAcc = TreeAccount::create([
            'code' => $p . '1000224',
            'name' => 'مواد لدى مندوب اختبار',
            'type' => 'asset',
            'level' => 3,
            'parent_id' => $assetRoot->id,
            'detail_type' => 'inventory_subcontract',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $expenseRoot = TreeAccount::create([
            'code' => $p . '50001',
            'name' => 'مصروفات اختبار',
            'type' => 'expense',
            'level' => 2,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->serviceExpenseAcc = TreeAccount::create([
            'code' => $p . '500017',
            'name' => 'مصاريف تشغيل اختبار',
            'type' => 'expense',
            'level' => 3,
            'parent_id' => $expenseRoot->id,
            'detail_type' => 'subcontract_service',
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $liabilityRoot = TreeAccount::create([
            'code' => $p . '2000',
            'name' => 'خصوم اختبار',
            'type' => 'liability',
            'level' => 1,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);

        $this->supplierAcc = TreeAccount::create([
            'code' => $p . '2101',
            'name' => 'ذمة مورد اختبار',
            'type' => 'liability',
            'level' => 3,
            'parent_id' => $liabilityRoot->id,
            'balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
        ]);
    }

    private function seedProcessingTransactionTypes(): void
    {
        $types = [
            ['name' => 'Processing Order', 'code' => 'PROCESSING_ORDER', 'prefix' => 'SUB'],
            ['name' => 'Processing Dispatch', 'code' => 'PROCESSING_DISPATCH', 'prefix' => 'MDN'],
            ['name' => 'Processing Receipt', 'code' => 'PROCESSING_RECEIPT', 'prefix' => 'PR'],
            ['name' => 'Processing Invoice', 'code' => 'PROCESSING_INVOICE', 'prefix' => 'PVI'],
        ];

        foreach ($types as $row) {
            $type = TransactionType::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'prefix' => $row['prefix'],
                    'affects_stock' => str_contains($row['code'], 'ORDER') || str_contains($row['code'], 'INVOICE') ? false : true,
                    'stock_direction' => 'neutral',
                    'is_active' => true,
                ]
            );

            DocumentSequence::query()->firstOrCreate(
                ['transaction_type_id' => $type->id],
                ['prefix' => $type->prefix, 'last_number' => 1000]
            );
        }
    }

    private function seedOperationalData(): void
    {
        $this->rawStock = Stock::query()->where('warehouse_type', 'raw_materials')->first()
            ?? Stock::query()->where('name', 'مخزن مواد خام')->first();

        if (! $this->rawStock) {
            $this->rawStock = Stock::query()->create([
                'name' => 'مخزن مواد خام ' . $this->codePrefix,
                'name_en' => 'Raw materials warehouse',
                'balance' => 0,
                'asset_id' => $this->rawInventoryAcc->id,
                'active' => true,
                'warehouse_type' => 'raw_materials',
            ]);
        } elseif (! $this->rawStock->asset_id) {
            $this->rawStock->update(['asset_id' => $this->rawInventoryAcc->id]);
            $this->rawStock->refresh();
        }

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'مورد تشغيل اختبار ' . $this->codePrefix,
            'supplier_phone' => '01000000000',
            'supplier_address' => 'test',
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $this->supplierAcc->id,
        ]);

        $existingCategory = Category::query()
            ->where('stock_id', $this->rawStock->id)
            ->where('quantity', '>=', 5)
            ->orderByDesc('quantity')
            ->first();

        if ($existingCategory) {
            $this->category = $existingCategory;

            return;
        }

        $productionId = DB::table('productions')->value('id');
        $measurementId = DB::table('measurements')->value('id');
        $this->assertNotNull($productionId);
        $this->assertNotNull($measurementId);

        $this->category = Category::query()->create([
            'category_name' => 'صنف خام اختبار ' . $this->codePrefix,
            'category_price' => 50,
            'unit_price' => 50,
            'total_price' => 0,
            'sell_total_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => $this->rawStock->name,
            'stock_id' => $this->rawStock->id,
            'production_id' => $productionId,
            'measurement_id' => $measurementId,
            'category_image' => 'no-image.png',
            'status' => '1',
            'product_type' => 'raw_material',
        ]);

        app(InventoryMovementLedgerService::class)->recordInbound(
            $this->category,
            InventoryMovementType::OpeningBalance,
            10,
            50,
            500,
            true,
            'processing_test',
            null,
            'رصيد افتتاحي لاختبار محاسبة الصرف',
            null,
            'test'
        );
        $this->category->refresh();

        $this->representative = ShippingCompany::query()->create([
            'name' => 'مندوب اختبار ' . $this->codePrefix,
            'type' => 'مندوب',
            'orders_count' => 0,
            'refused_orders_percentage' => 0,
        ]);
    }
}
