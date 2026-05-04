<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Services\Items\CostCalculationService;
use App\Services\Items\InventoryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the recipe/BOM/inventory workflow.
 *
 * These tests run against the real database inside a transaction that
 * is rolled back after each test — no tables are dropped or recreated.
 */
class RecipeWorkflowTest extends TestCase
{
    private Production $production;
    private Measurement $measurement;
    private Stock $rawStock;
    private Stock $finishedStock;

    /** Track IDs for manual cleanup as a safety net. */
    private array $createdCategoryIds = [];
    private array $createdRecipeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->production = Production::first() ?? Production::create([
            'warehouse'       => 'مخزن مواد خام',
            'production_line' => 'خط إنتاج افتراضي',
        ]);

        $this->measurement = Measurement::first() ?? Measurement::create([
            'unit'      => 'متر',
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

    // ──────────────────────────────────────────────────────────
    // Helper factories
    // ──────────────────────────────────────────────────────────

    private function makeRawMaterial(string $name, float $price, float $qty): Category
    {
        $cat = Category::create([
            'category_name'    => $name . '_' . uniqid(),
            'category_price'   => $price,
            'unit_price'       => $price,
            'initial_balance'  => $qty,
            'minimum_quantity' => 0,
            'warehouse'        => 'مخزن مواد خام',
            'production_id'    => $this->production->id,
            'measurement_id'   => $this->measurement->id,
            'stock_id'         => $this->rawStock->id,
            'category_image'   => '',
        ]);
        $cat->quantity = $qty;
        $cat->total_price = $price * $qty;
        $cat->save();
        $this->createdCategoryIds[] = $cat->id;
        return $cat;
    }

    private function makeFinishedGood(string $name, float $sellPrice): Category
    {
        $cat = Category::create([
            'category_name'    => $name . '_' . uniqid(),
            'category_price'   => $sellPrice,
            'unit_price'       => $sellPrice,
            'sell_total_price' => 0,
            'initial_balance'  => 0,
            'minimum_quantity' => 0,
            'warehouse'        => 'مخزن منتج تام',
            'production_id'    => $this->production->id,
            'measurement_id'   => $this->measurement->id,
            'stock_id'         => $this->finishedStock->id,
            'category_image'   => '',
        ]);
        $cat->quantity = 0;
        $cat->save();
        $this->createdCategoryIds[] = $cat->id;
        return $cat;
    }

    private function makeRecipe(string $name, array $ingredientSpecs, array $extraCostSpecs = []): Recipe
    {
        $recipe = Recipe::create([
            'recipe_name' => $name . '_' . uniqid(),
            'description' => "Test recipe: {$name}",
        ]);
        $this->createdRecipeIds[] = $recipe->id;

        foreach ($ingredientSpecs as $spec) {
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'item_id'   => $spec['item_id'],
                'quantity'  => $spec['quantity'],
                'unit_cost' => $spec['unit_cost'],
            ]);
        }

        foreach ($extraCostSpecs as $spec) {
            RecipeExtraCost::create([
                'recipe_id' => $recipe->id,
                'name'      => $spec['name'],
                'type'      => $spec['type'],
                'value'     => $spec['value'],
            ]);
        }

        return $recipe->fresh(['ingredients', 'extraCosts']);
    }

    // ══════════════════════════════════════════════════════════
    // TASK 1: Data Validation — raw materials, quantities, costs
    // ══════════════════════════════════════════════════════════

    public function test_raw_materials_created_correctly(): void
    {
        $mat = $this->makeRawMaterial('قماش قطن', 15.50, 100);

        $this->assertDatabaseHas('categories', [
            'id'        => $mat->id,
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->assertEquals(15.50, (float) $mat->category_price);
        $this->assertEquals(100, (float) $mat->quantity);
    }

    public function test_quantities_and_units_stored_correctly(): void
    {
        $mat = $this->makeRawMaterial('خيط', 3.2500, 250.5);

        $this->assertEquals(250.5, (float) $mat->quantity);
        $this->assertEquals(3.25, (float) $mat->unit_price);
        $this->assertNotNull($mat->measurement_id);
    }

    public function test_total_cost_equals_sum_of_materials(): void
    {
        $mat1 = $this->makeRawMaterial('قماش', 10, 100);
        $mat2 = $this->makeRawMaterial('خيط', 5, 200);

        $recipe = $this->makeRecipe('فستان', [
            ['item_id' => $mat1->id, 'quantity' => '2', 'unit_cost' => '10'],
            ['item_id' => $mat2->id, 'quantity' => '3', 'unit_cost' => '5'],
        ]);

        $costService = new CostCalculationService();
        $result = $costService->calculateFinalCost($recipe->ingredients, []);

        // 2*10 + 3*5 = 20 + 15 = 35
        $this->assertSame('35.0000', $result['materials_cost']);
        $this->assertSame('35.0000', $result['final_cost']);
    }

    public function test_no_negative_quantities_validation(): void
    {
        $costService = new CostCalculationService();

        $errors = $costService->validateIngredients([
            ['item_id' => 1, 'quantity' => '-5', 'unit_cost' => '10'],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_no_missing_material_references(): void
    {
        $costService = new CostCalculationService();

        $errors = $costService->validateIngredients([
            ['item_id' => null, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_unit_consistency_from_measurement(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $mat->load('measurement');

        $this->assertNotNull($mat->measurement);
        $this->assertNotEmpty($mat->measurement->unit);
    }

    // ══════════════════════════════════════════════════════════
    // TASK 2: Inventory Logic
    // ══════════════════════════════════════════════════════════

    public function test_recipe_execution_deducts_raw_materials(): void
    {
        $mat1 = $this->makeRawMaterial('قماش', 10, 100);
        $mat2 = $this->makeRawMaterial('خيط', 5, 200);
        $fg   = $this->makeFinishedGood('فستان تام', 50);

        $recipe = $this->makeRecipe('وصفة فستان', [
            ['item_id' => $mat1->id, 'quantity' => '2',   'unit_cost' => '10'],
            ['item_id' => $mat2->id, 'quantity' => '3.5', 'unit_cost' => '5'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');

        $mat1->refresh();
        $mat2->refresh();

        $this->assertEquals(98, (float) $mat1->quantity);     // 100 - 2
        $this->assertEquals(196.5, (float) $mat2->quantity);  // 200 - 3.5
    }

    public function test_recipe_execution_adds_finished_product(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 80);

        $recipe = $this->makeRecipe('وصفة قميص', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $inventoryService->executeRecipe($recipe, $fg->id, 3, 'test');

        $fg->refresh();
        $this->assertEquals(3, (float) $fg->quantity);
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 5); // only 5 in stock
        $fg  = $this->makeFinishedGood('قميص', 80);

        $recipe = $this->makeRecipe('وصفة قميص', [
            ['item_id' => $mat->id, 'quantity' => '6', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');

        $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');
    }

    public function test_stock_movements_are_logged(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 80);

        $recipe = $this->makeRecipe('وصفة قميص', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test_user');

        $this->assertCount(2, $result['movements']); // 1 out + 1 in

        $outMovement = collect($result['movements'])->firstWhere('direction', 'out');
        $this->assertNotNull($outMovement);
        $this->assertEquals('recipe', $outMovement->reference_type);
        $this->assertEquals($recipe->id, $outMovement->reference_id);
        $this->assertEquals('test_user', $outMovement->performed_by);
        $this->assertEquals(5, (float) $outMovement->quantity);

        $inMovement = collect($result['movements'])->firstWhere('direction', 'in');
        $this->assertNotNull($inMovement);
        $this->assertEquals(1, (float) $inMovement->quantity);
    }

    public function test_batch_execution_multiplies_quantities(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 80);

        $recipe = $this->makeRecipe('وصفة قميص', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $inventoryService->executeRecipe($recipe, $fg->id, 4, 'test');

        $mat->refresh();
        $fg->refresh();

        $this->assertEquals(80, (float) $mat->quantity);  // 100 - (5*4)
        $this->assertEquals(4, (float) $fg->quantity);
    }

    public function test_check_stock_availability_detects_shortage(): void
    {
        $mat1 = $this->makeRawMaterial('قماش', 10, 10);
        $mat2 = $this->makeRawMaterial('خيط', 5, 3);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat1->id, 'quantity' => '5', 'unit_cost' => '10'],
            ['item_id' => $mat2->id, 'quantity' => '5', 'unit_cost' => '5'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->checkStockAvailability($recipe, 1);

        $this->assertFalse($result['sufficient']);
        $this->assertCount(1, $result['shortages']);
        $this->assertEquals($mat2->id, $result['shortages'][0]['item_id']);
    }

    public function test_check_stock_availability_sufficient(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->checkStockAvailability($recipe, 1);

        $this->assertTrue($result['sufficient']);
        $this->assertEmpty($result['shortages']);
    }

    // ══════════════════════════════════════════════════════════
    // TASK 3: Dynamic Extra Costs
    // ══════════════════════════════════════════════════════════

    public function test_recipe_extra_costs_stored_in_database(): void
    {
        $recipe = Recipe::create(['recipe_name' => 'Test_' . uniqid()]);
        $this->createdRecipeIds[] = $recipe->id;

        RecipeExtraCost::create([
            'recipe_id' => $recipe->id,
            'name'      => 'Machine Cost',
            'type'      => 'fixed',
            'value'     => 150,
        ]);

        RecipeExtraCost::create([
            'recipe_id' => $recipe->id,
            'name'      => 'Waste %',
            'type'      => 'percentage',
            'value'     => 5,
        ]);

        $this->assertCount(2, $recipe->fresh()->extraCosts);
    }

    public function test_extra_cost_resolved_amount_fixed(): void
    {
        $extra = new RecipeExtraCost([
            'name'  => 'Labor',
            'type'  => 'fixed',
            'value' => 200,
        ]);

        $this->assertEquals(200, $extra->resolvedAmount(500));
        $this->assertEquals(200, $extra->resolvedAmount(0));
    }

    public function test_extra_cost_resolved_amount_percentage(): void
    {
        $extra = new RecipeExtraCost([
            'name'  => 'Waste',
            'type'  => 'percentage',
            'value' => 10,
        ]);

        $this->assertEquals(50, $extra->resolvedAmount(500));
        $this->assertEquals(0, $extra->resolvedAmount(0));
    }

    public function test_final_cost_includes_extra_costs(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '10', 'unit_cost' => '10'],
        ], [
            ['name' => 'Machine Cost', 'type' => 'fixed',      'value' => '50'],
            ['name' => 'Waste',        'type' => 'percentage', 'value' => '10'],
        ]);

        $costService = new CostCalculationService();
        $result = $costService->calculateFinalCost($recipe->ingredients, $recipe->extraCosts);

        // materials = 10*10 = 100, fixed = 50, pct = 10, final = 160
        $this->assertSame('100.0000', $result['materials_cost']);
        $this->assertSame('50.0000',  $result['fixed_costs']);
        $this->assertSame('10.0000',  $result['percentage_costs']);
        $this->assertSame('160.0000', $result['final_cost']);
    }

    // ══════════════════════════════════════════════════════════
    // TASK 4: Total Cost & Margin Fix
    // ══════════════════════════════════════════════════════════

    public function test_margin_calculation_correct(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 200);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '10', 'unit_cost' => '10'],
        ], [
            ['name' => 'Labor', 'type' => 'fixed', 'value' => '50'],
        ]);

        $fg->recipe_id = $recipe->id;
        $fg->save();

        $costService = new CostCalculationService();
        $breakdown = $costService->breakdownForRecipe($recipe);

        // materials = 100, fixed = 50, final = 150
        // selling = 200, margin = (200-150)/200 = 0.25
        $this->assertSame('150.0000', $breakdown['final_cost']);
        $this->assertSame('0.250000', $breakdown['margin_percent']);
    }

    public function test_total_cost_includes_raw_materials_and_extras(): void
    {
        $mat = $this->makeRawMaterial('خامة', 20, 50);

        $recipe = $this->makeRecipe('وصفة مع إضافات', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '20'],
        ], [
            ['name' => 'Electricity', 'type' => 'fixed',      'value' => '25'],
            ['name' => 'Overhead',    'type' => 'percentage', 'value' => '15'],
        ]);

        $costService = new CostCalculationService();
        $result = $costService->calculateFinalCost($recipe->ingredients, $recipe->extraCosts);

        // materials = 5*20 = 100, fixed = 25, pct = 15, final = 140
        $this->assertSame('140.0000', $result['final_cost']);
    }

    public function test_no_negative_margin_on_healthy_recipe(): void
    {
        $costService = new CostCalculationService();
        $margin = $costService->marginPercent('200', '100');

        $this->assertTrue(bccomp($margin, '0', 4) > 0);
    }

    // ══════════════════════════════════════════════════════════
    // TASK 5: Edge Cases
    // ══════════════════════════════════════════════════════════

    public function test_missing_material_price_detected(): void
    {
        $costService = new CostCalculationService();

        $errors = $costService->validateIngredients([
            ['item_id' => 1, 'quantity' => '5', 'unit_cost' => ''],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_zero_quantity_detected(): void
    {
        $costService = new CostCalculationService();

        $errors = $costService->validateIngredients([
            ['item_id' => 1, 'quantity' => '0', 'unit_cost' => '10'],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_duplicate_materials_in_recipe_detected(): void
    {
        $costService = new CostCalculationService();

        $errors = $costService->validateIngredients([
            ['item_id' => 42, 'quantity' => '5', 'unit_cost' => '10'],
            ['item_id' => 42, 'quantity' => '3', 'unit_cost' => '10'],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_recipe_creates_finished_product(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('منتج تام', 50);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '10', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');

        $this->assertEquals($fg->id, $result['produced']['item_id']);
        $this->assertEquals(1, $result['produced']['quantity']);
    }

    public function test_execution_with_empty_recipe_fails(): void
    {
        $fg = $this->makeFinishedGood('منتج', 50);

        $recipe = Recipe::create(['recipe_name' => 'Empty_' . uniqid()]);
        $this->createdRecipeIds[] = $recipe->id;

        $inventoryService = app(InventoryService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no ingredients/');

        $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');
    }

    public function test_execution_with_zero_batch_qty_fails(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('منتج', 50);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/at least 1/');

        $inventoryService->executeRecipe($recipe, $fg->id, 0, 'test');
    }

    public function test_movements_for_reference(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 80);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '5', 'unit_cost' => '10'],
        ]);

        $inventoryService = app(InventoryService::class);
        $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');

        $movements = $inventoryService->movementsForReference('recipe', $recipe->id);
        $this->assertCount(2, $movements);
    }

    public function test_inventory_execution_includes_extra_costs_in_finished_product_value(): void
    {
        $mat = $this->makeRawMaterial('قماش', 10, 100);
        $fg  = $this->makeFinishedGood('قميص', 200);

        $recipe = $this->makeRecipe('وصفة', [
            ['item_id' => $mat->id, 'quantity' => '10', 'unit_cost' => '10'],
        ], [
            ['name' => 'Labor', 'type' => 'fixed', 'value' => '50'],
        ]);

        $inventoryService = app(InventoryService::class);
        $result = $inventoryService->executeRecipe($recipe, $fg->id, 1, 'test');

        // final_cost should be 100 (materials) + 50 (labor) = 150
        $this->assertEquals(150, (float) $result['produced']['final_cost']);
    }
}
