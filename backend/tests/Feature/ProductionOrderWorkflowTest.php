<?php

namespace Tests\Feature;

use App\Enums\ProductionOrderStatus;
use App\Models\Category;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionOrderWorkflowTest extends TestCase
{
    private Production $production;

    private Measurement $measurement;

    private Stock $rawStock;

    private Stock $finishedStock;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'خط إنتاج افتراضي',
        ]);

        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->rawStock = Stock::where('name', 'مخزن مواد خام')->first()
            ?? Stock::create(['name' => 'مخزن مواد خام', 'balance' => 0, 'asset_id' => 0]);

        $this->finishedStock = Stock::where('name', 'مخزن منتج تام')->first()
            ?? Stock::create(['name' => 'مخزن منتج تام', 'balance' => 0, 'asset_id' => 0]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_draft_start_complete_finished_good_carries_cost(): void
    {
        if (! Schema::hasColumn('categories', 'product_type')) {
            $this->markTestSkipped('Migration not applied: categories.product_type');
        }

        $raw = Category::create([
            'category_name' => 'RM_PO_' . uniqid(),
            'category_price' => 4,
            'unit_price' => 4,
            'initial_balance' => 100,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->rawStock->id,
            'category_image' => '',
            'product_type' => 'raw_material',
        ]);
        $raw->quantity = 100;
        $raw->total_price = 400;
        $raw->save();

        $fg = Category::create([
            'category_name' => 'FG_PO_' . uniqid(),
            'category_price' => 20,
            'unit_price' => 0,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
            'quantity' => 0,
            'sell_total_price' => 0,
            'product_type' => 'finished',
        ]);

        $recipe = Recipe::create([
            'recipe_name' => 'R_PO_' . uniqid(),
            'output_item_id' => $fg->id,
        ]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id' => $raw->id,
            'quantity' => 2,
            'unit_cost' => null,
        ]);
        $fg->recipe_id = $recipe->id;
        $fg->save();

        /** @var ProductionOrderLifecycleService $lifecycle */
        $lifecycle = app(ProductionOrderLifecycleService::class);

        $order = $lifecycle->createDraft((int) $recipe->id, (int) $fg->id, '5', 'test', $this->user->id);
        $this->assertSame(ProductionOrderStatus::Draft, $order->status);

        $lifecycle->start($order, $this->user->id);
        $raw->refresh();
        $this->assertEqualsWithDelta(90.0, (float) $raw->quantity, 0.0001);

        $lifecycle->complete(ProductionOrder::findOrFail($order->id), $this->user->id);
        $fg->refresh();
        $order->refresh();

        $this->assertSame(ProductionOrderStatus::Completed, $order->status);
        $this->assertEqualsWithDelta(5.0, (float) $fg->quantity, 0.0001);
        $this->assertNotNull($order->total_output_cost);
        $this->assertGreaterThan(0, (float) $order->total_output_cost);
    }
}
