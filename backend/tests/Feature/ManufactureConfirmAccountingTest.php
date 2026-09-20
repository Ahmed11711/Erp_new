<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DailyEntry;
use App\Models\DailyEntryItem;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Stock;
use App\Models\TreeAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManufactureConfirmAccountingTest extends TestCase
{
    private User $admin;

    private Production $production;

    private Measurement $measurement;

    private Stock $rawStock;

    private Stock $finishedStock;

    private Stock $wipStock;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->admin = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'test',
        ]);
        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->rawStock = Stock::where('name', 'مخزن مواد خام')->first()
            ?? Stock::create(['name' => 'مخزن مواد خام', 'balance' => 0, 'asset_id' => 0]);
        $this->finishedStock = Stock::where('name', 'مخزن منتج تام')->first()
            ?? Stock::create(['name' => 'مخزن منتج تام', 'balance' => 0, 'asset_id' => 0]);
        $this->wipStock = Stock::where('name', 'مخزن منتج تحت التشغيل')->first()
            ?? Stock::create(['name' => 'مخزن منتج تحت التشغيل', 'balance' => 0, 'asset_id' => 0]);

        $this->linkWarehouseAccounts();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_completed_finished_goods_posts_directly_from_raw_and_scales_stale_unit_qty(): void
    {
        [$fg, $raw] = $this->makeFinishedWithRawBom(2, 10);
        $rawBefore = (float) $raw->quantity;
        $fgBefore = (float) $fg->quantity;

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/manufacture/confirm', [
            'product_id' => $fg->id,
            'quantity' => 2,
            'status' => 'تم الانتهاء',
            'total' => 40,
            'date' => now()->toDateString(),
            'consumption_lines' => [[
                'bom_item_id' => (int) $raw->id,
                'resolved_category_id' => (int) $raw->id,
                'quantity' => 2,
            ]],
        ]);

        $response->assertCreated();
        $orderId = (int) $response->json('id');
        $this->assertGreaterThan(0, $orderId);

        $raw->refresh();
        $fg->refresh();
        $this->assertEqualsWithDelta($rawBefore - 4, (float) $raw->quantity, 0.0001);
        $this->assertEqualsWithDelta($fgBefore + 2, (float) $fg->quantity, 0.0001);

        $entries = DailyEntry::query()
            ->where(function ($q) use ($orderId) {
                $q->where('description', 'like', '%أمر #' . $orderId . '%')
                    ->orWhere('description', 'like', '%أمر تصنيع #' . $orderId . '%');
            })
            ->get();

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('إتمام تصنيع', (string) $entries->first()->description);

        $items = DailyEntryItem::query()->where('daily_entry_id', $entries->first()->id)->get();
        $fgAcc = TreeAccount::resolveInventoryAccountForStock($this->finishedStock);
        $rawAcc = TreeAccount::resolveInventoryAccountForStock($this->rawStock);
        $wipAcc = TreeAccount::resolveInventoryAccountForStock($this->wipStock);
        $this->assertNotNull($fgAcc);
        $this->assertNotNull($rawAcc);

        $this->assertEqualsWithDelta(40.0, (float) $items->where('account_id', $fgAcc->id)->sum('debit'), 0.02);
        $this->assertEqualsWithDelta(40.0, (float) $items->where('account_id', $rawAcc->id)->sum('credit'), 0.02);
        if ($wipAcc) {
            $this->assertEqualsWithDelta(0.0, (float) $items->where('account_id', $wipAcc->id)->sum('debit'), 0.02);
            $this->assertEqualsWithDelta(0.0, (float) $items->where('account_id', $wipAcc->id)->sum('credit'), 0.02);
        }
    }

    public function test_in_progress_finished_goods_still_absorbs_into_wip(): void
    {
        [$fg, $raw] = $this->makeFinishedWithRawBom(2, 10);

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/manufacture/confirm', [
            'product_id' => $fg->id,
            'quantity' => 1,
            'status' => 'في التصنيع',
            'total' => 20,
            'date' => now()->toDateString(),
        ]);

        $response->assertCreated();
        $orderId = (int) $response->json('id');

        $entries = DailyEntry::query()
            ->where('description', 'like', '%أمر تصنيع #' . $orderId . '%')
            ->get();
        $this->assertCount(1, $entries);
        $this->assertStringContainsString('استهلاك مواد خام', (string) $entries->first()->description);

        $items = DailyEntryItem::query()->where('daily_entry_id', $entries->first()->id)->get();
        $wipAcc = TreeAccount::resolveInventoryAccountForStock($this->wipStock);
        $rawAcc = TreeAccount::resolveInventoryAccountForStock($this->rawStock);
        $this->assertNotNull($wipAcc);
        $this->assertNotNull($rawAcc);
        $this->assertEqualsWithDelta(20.0, (float) $items->where('account_id', $wipAcc->id)->sum('debit'), 0.02);
        $this->assertEqualsWithDelta(20.0, (float) $items->where('account_id', $rawAcc->id)->sum('credit'), 0.02);
    }

    /**
     * @return array{0: Category, 1: Category}
     */
    private function makeFinishedWithRawBom(float $bomQty, float $unitCost): array
    {
        $fg = Category::create([
            'category_name' => 'FG_MFG_' . uniqid(),
            'category_price' => 100,
            'unit_price' => $bomQty * $unitCost,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'product_type' => 'finished',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
            'quantity' => 0,
            'total_price' => 0,
            'sell_total_price' => 0,
        ]);

        $raw = Category::create([
            'category_name' => 'RM_MFG_' . uniqid(),
            'category_price' => $unitCost,
            'unit_price' => $unitCost,
            'initial_balance' => 100,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'product_type' => 'raw_material',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->rawStock->id,
            'category_image' => '',
            'quantity' => 100,
            'total_price' => 100 * $unitCost,
        ]);

        $manufacture = Manufacture::create([
            'product_id' => $fg->id,
            'total' => $bomQty * $unitCost,
        ]);
        ManufactureProduct::create([
            'manufacture_id' => $manufacture->id,
            'product_id' => $raw->id,
            'quantity' => $bomQty,
            'total_price' => $bomQty * $unitCost,
        ]);

        return [$fg, $raw];
    }

    private function linkWarehouseAccounts(): void
    {
        $rawAcc = TreeAccount::resolveInventoryRawAccount();
        $fgAcc = TreeAccount::resolveInventoryFinishedAccount();
        $wipAcc = TreeAccount::resolveInventoryWipAccount();

        if ($rawAcc && ! $this->rawStock->asset_id) {
            $this->rawStock->asset_id = $rawAcc->id;
            $this->rawStock->warehouse_type = 'raw_materials';
            $this->rawStock->save();
        }
        if ($fgAcc && ! $this->finishedStock->asset_id) {
            $this->finishedStock->asset_id = $fgAcc->id;
            $this->finishedStock->warehouse_type = 'finished_goods';
            $this->finishedStock->save();
        }
        if ($wipAcc && ! $this->wipStock->asset_id) {
            $this->wipStock->asset_id = $wipAcc->id;
            $this->wipStock->warehouse_type = 'wip';
            $this->wipStock->save();
        }
    }
}
