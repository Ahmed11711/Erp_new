<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\DocumentSequence;
use App\Models\ShippingCompany;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\TransactionType;
use App\Models\TreeAccount;
use App\Models\User;
use App\Services\Inventory\InventoryMovementLedgerService;
use App\Services\Processing\ProcessingDispatchService;
use App\Services\Processing\ProcessingWarehouseResolver;
use App\Services\Processing\ProcessingWarehouseStockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessingWarehouseStockServiceTest extends TestCase
{
    use DatabaseTransactions;

    private string $codePrefix = '';

    private User $user;

    private TreeAccount $rawInventoryAcc;

    private TreeAccount $transitInventoryAcc;

    private TreeAccount $supplierAcc;

    private Stock $rawStock;

    private Supplier $supplier;

    private Category $category;

    private ShippingCompany $representative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codePrefix = 'PW' . substr(str_replace('.', '', uniqid('', true)), -8);
        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);
        $this->actingAs($this->user, 'api');

        $this->seedChart();
        $this->seedProcessingTransactionTypes();
        $this->seedOperationalData();
    }

    public function test_at_vendor_stock_uses_the_processing_warehouse_name(): void
    {
        $stock = ProcessingWarehouseResolver::ensureMaterialsAtVendorStock();

        $this->assertSame(ProcessingWarehouseResolver::WAREHOUSE_NAME, $stock->name);
        $this->assertSame('materials_at_vendor', $stock->warehouse_type);
    }

    public function test_posted_dispatch_shows_up_in_the_processing_warehouse(): void
    {
        $qty = 3.0;
        $this->submitVoucher($qty);

        $service = app(ProcessingWarehouseStockService::class);
        $overview = $service->overview(['supplier_id' => $this->supplier->id]);

        $this->assertSame(ProcessingWarehouseResolver::WAREHOUSE_NAME, $overview['warehouse']['name']);
        $this->assertEqualsWithDelta($qty, $overview['kpis']['total_quantity'], 0.001);
        $this->assertSame(1, $overview['kpis']['vendors_count']);
        $this->assertSame(1, $overview['kpis']['orders_count']);
        $this->assertGreaterThan(0, $overview['kpis']['total_value']);

        $vendor = $overview['vendors'][0];
        $this->assertSame($this->supplier->id, $vendor['supplier_id']);
        $this->assertEqualsWithDelta($qty, $vendor['quantity'], 0.001);
        $this->assertCount(1, $vendor['lines']);

        $line = $vendor['lines'][0];
        $this->assertSame($this->category->id, $line['category_id']);
        $this->assertEqualsWithDelta($qty, $line['qty_at_vendor'], 0.001);
        $this->assertEqualsWithDelta(round($qty * $line['unit_cost'], 2), $line['value_at_vendor'], 0.02);
        $this->assertSame('0-30', $line['aging_bucket']);

        $this->assertSame($this->category->id, $overview['items'][0]['category_id']);

        $fresh = $overview['aging'][0];
        $this->assertSame('0-30', $fresh['bucket']);
        $this->assertEqualsWithDelta($qty, $fresh['quantity'], 0.001);
    }

    public function test_movement_log_lists_the_dispatch_as_an_inbound_movement(): void
    {
        $qty = 2.0;
        $result = $this->submitVoucher($qty);

        $movements = app(ProcessingWarehouseStockService::class)
            ->movements(['supplier_id' => $this->supplier->id]);

        $this->assertCount(1, $movements);
        $this->assertSame('in', $movements[0]['direction']);
        $this->assertSame('dispatch', $movements[0]['document_type']);
        $this->assertSame($result['dispatch']->dispatch_number, $movements[0]['document_number']);
        $this->assertEqualsWithDelta($qty, $movements[0]['quantity'], 0.001);
    }

    public function test_filtering_by_another_supplier_excludes_the_balance(): void
    {
        $this->submitVoucher(1.0);

        $overview = app(ProcessingWarehouseStockService::class)
            ->overview(['supplier_id' => $this->supplier->id + 999999]);

        $this->assertSame(0.0, $overview['kpis']['total_quantity']);
        $this->assertSame([], $overview['vendors']);
    }

    /**
     * @return array<string, mixed>
     */
    private function submitVoucher(float $qty): array
    {
        return app(ProcessingDispatchService::class)->submitVoucher([
            'supplier_id' => $this->supplier->id,
            'source_stock_id' => $this->rawStock->id,
            'dispatch_date' => now()->toDateString(),
            'dispatch_type' => 'goods_to_supplier',
            'representative_type' => 'internal',
            'shipping_company_id' => $this->representative->id,
            'lines' => [[
                'category_id' => $this->category->id,
                'ordered_qty' => $qty,
                'expected_service_amount' => 25,
            ]],
        ]);
    }

    private function seedChart(): void
    {
        $p = $this->codePrefix;

        $assetRoot = TreeAccount::create([
            'code' => $p . '1000',
            'name' => 'أصول اختبار مخزن التجهيز',
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
            'name' => 'مخزن التجهيز اختبار',
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

        TreeAccount::create([
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
                    'affects_stock' => ! str_contains($row['code'], 'ORDER') && ! str_contains($row['code'], 'INVOICE'),
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

        Stock::query()->firstOrCreate(
            ['warehouse_type' => 'materials_at_vendor'],
            [
                'name' => ProcessingWarehouseResolver::WAREHOUSE_NAME,
                'name_en' => ProcessingWarehouseResolver::WAREHOUSE_NAME_EN,
                'balance' => 0,
                'asset_id' => $this->transitInventoryAcc->id,
                'active' => true,
            ]
        );

        $this->supplier = Supplier::query()->create([
            'supplier_name' => 'معالج اختبار ' . $this->codePrefix,
            'supplier_phone' => '01000000000',
            'supplier_address' => 'test',
            'balance' => 0,
            'last_balance' => 0,
            'tree_account_id' => $this->supplierAcc->id,
        ]);

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
            20,
            50,
            1000,
            true,
            'processing_warehouse_test',
            null,
            'رصيد افتتاحي لاختبار مخزن التجهيز',
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
