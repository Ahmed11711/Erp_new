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
use App\Models\User;
use App\Enums\InventoryMovementType;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for Recipe CRUD endpoints and extra cost import logic.
 */
class RecipeCrudTest extends TestCase
{
    private Production $production;
    private Measurement $measurement;
    private Stock $rawStock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->user = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

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
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function makeRawMaterial(string $name, float $price): Category
    {
        $cat = Category::create([
            'category_name'   => $name . '_' . uniqid(),
            'category_price'  => $price,
            'unit_price'      => $price,
            'initial_balance' => 100,
            'minimum_quantity' => 0,
            'warehouse'       => 'مخزن مواد خام',
            'production_id'   => $this->production->id,
            'measurement_id'  => $this->measurement->id,
            'stock_id'        => $this->rawStock->id,
            'category_image'  => '',
        ]);
        $cat->quantity = 100;
        $cat->total_price = $price * 100;
        $cat->save();

        return $cat;
    }

    // ══════════════════════════════════════════════════════════
    // Recipe Index (with counts)
    // ══════════════════════════════════════════════════════════

    public function test_recipe_index_returns_counts(): void
    {
        $recipe = Recipe::create(['recipe_name' => 'TestCounts_' . uniqid()]);
        $mat = $this->makeRawMaterial('mat', 10);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id'   => $mat->id,
            'quantity'  => 5,
            'unit_cost' => 10,
        ]);

        RecipeExtraCost::create([
            'recipe_id' => $recipe->id,
            'name'      => 'Labor',
            'type'      => 'fixed',
            'value'     => 50,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/recipes');
        $response->assertOk();

        $found = collect($response->json())->firstWhere('id', $recipe->id);
        $this->assertNotNull($found);
        $this->assertEquals(1, $found['ingredients_count']);
        $this->assertEquals(1, $found['extra_costs_count']);
    }

    // ══════════════════════════════════════════════════════════
    // Create Recipe
    // ══════════════════════════════════════════════════════════

    public function test_create_recipe_with_ingredients_and_extras(): void
    {
        $mat1 = $this->makeRawMaterial('قماش', 10);
        $mat2 = $this->makeRawMaterial('خيط', 5);

        $response = $this->actingAs($this->user)->postJson('/api/recipes', [
            'recipe_name' => 'وصفة جديدة_' . uniqid(),
            'ingredients' => [
                ['item_id' => $mat1->id, 'quantity' => 2, 'unit_cost' => 10],
                ['item_id' => $mat2->id, 'quantity' => 3, 'unit_cost' => 5],
            ],
            'extra_costs' => [
                ['name' => 'Machine', 'type' => 'fixed', 'value' => 50],
                ['name' => 'Waste', 'type' => 'percentage', 'value' => 5],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('recipe.id'));
        $this->assertCount(2, $response->json('recipe.ingredients'));
        $this->assertCount(2, $response->json('recipe.extra_costs'));

        // materials = 20 + 15 = 35, fixed = 50, pct = 5% of 35 = 1.75, final = 86.75
        $this->assertSame('86.7500', $response->json('breakdown.final_cost'));
    }

    public function test_create_recipe_validation_requires_ingredients(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/recipes', [
            'recipe_name' => 'NoIngredients',
            'ingredients' => [],
        ]);

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════
    // Update Recipe
    // ══════════════════════════════════════════════════════════

    public function test_update_recipe_replaces_ingredients(): void
    {
        $mat1 = $this->makeRawMaterial('قماش', 10);
        $mat2 = $this->makeRawMaterial('خيط', 5);

        $recipe = Recipe::create(['recipe_name' => 'ToUpdate_' . uniqid()]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id'   => $mat1->id,
            'quantity'  => 5,
            'unit_cost' => 10,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/recipes/{$recipe->id}", [
            'recipe_name' => 'Updated Name',
            'ingredients' => [
                ['item_id' => $mat2->id, 'quantity' => 10, 'unit_cost' => 5],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals('Updated Name', $response->json('recipe.recipe_name'));
        $this->assertCount(1, $response->json('recipe.ingredients'));
        $this->assertEquals($mat2->id, $response->json('recipe.ingredients.0.item_id'));
    }

    public function test_update_recipe_replaces_extra_costs(): void
    {
        $mat = $this->makeRawMaterial('mat', 10);
        $recipe = Recipe::create(['recipe_name' => 'ECUpdate_' . uniqid()]);
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id'   => $mat->id,
            'quantity'  => 10,
            'unit_cost' => 10,
        ]);
        RecipeExtraCost::create([
            'recipe_id' => $recipe->id,
            'name'      => 'Old Cost',
            'type'      => 'fixed',
            'value'     => 100,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/recipes/{$recipe->id}", [
            'extra_costs' => [
                ['name' => 'New Machine', 'type' => 'fixed', 'value' => 200],
                ['name' => 'Waste', 'type' => 'percentage', 'value' => 10],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('recipe.extra_costs'));
        $this->assertDatabaseMissing('recipe_extra_costs', [
            'recipe_id' => $recipe->id, 'name' => 'Old Cost',
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // Delete Recipe
    // ══════════════════════════════════════════════════════════

    public function test_delete_recipe_removes_all_related_data(): void
    {
        $mat = $this->makeRawMaterial('mat', 10);
        $recipe = Recipe::create(['recipe_name' => 'ToDelete_' . uniqid()]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id'   => $mat->id,
            'quantity'  => 5,
            'unit_cost' => 10,
        ]);

        RecipeExtraCost::create([
            'recipe_id' => $recipe->id,
            'name'      => 'Labor',
            'type'      => 'fixed',
            'value'     => 50,
        ]);

        StockMovement::create([
            'category_id'    => $mat->id,
            'warehouse_stock_id' => $mat->stock_id,
            'warehouse_name' => 'مخزن مواد خام',
            'direction'      => 'out',
            'movement_type'  => InventoryMovementType::RecipeExecution->value,
            'quantity'       => 5,
            'unit_cost'      => 10,
            'total_cost'     => 50,
            'reference_type' => 'recipe',
            'reference_id'   => $recipe->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/recipes/{$recipe->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_ingredients', ['recipe_id' => $recipe->id]);
        $this->assertDatabaseMissing('recipe_extra_costs', ['recipe_id' => $recipe->id]);
        $this->assertEquals(0, StockMovement::where('reference_type', 'recipe')
            ->where('reference_id', $recipe->id)->count());
    }

    // ══════════════════════════════════════════════════════════
    // Bulk Delete
    // ══════════════════════════════════════════════════════════

    public function test_bulk_delete_multiple_recipes(): void
    {
        $r1 = Recipe::create(['recipe_name' => 'Bulk1_' . uniqid()]);
        $r2 = Recipe::create(['recipe_name' => 'Bulk2_' . uniqid()]);
        $r3 = Recipe::create(['recipe_name' => 'Bulk3_' . uniqid()]);

        $response = $this->actingAs($this->user)->postJson('/api/recipes/bulk-delete', [
            'ids' => [$r1->id, $r2->id],
        ]);

        $response->assertOk();
        $this->assertEquals(2, $response->json('deleted'));

        $this->assertDatabaseMissing('recipes', ['id' => $r1->id]);
        $this->assertDatabaseMissing('recipes', ['id' => $r2->id]);
        $this->assertDatabaseHas('recipes', ['id' => $r3->id]);
    }

    // ══════════════════════════════════════════════════════════
    // Extra cost import detection
    // ══════════════════════════════════════════════════════════

    public function test_extra_cost_import_detection_logic(): void
    {
        $service = app(\App\Services\Items\RecipeSheetImportService::class);

        $reflection = new \ReflectionClass($service);

        $isExtra = $reflection->getMethod('isExtraCostLabel');
        $isExtra->setAccessible(true);

        $this->assertTrue($isExtra->invoke($service, 'سعر المكن'));
        $this->assertTrue($isExtra->invoke($service, 'سعر القص'));
        $this->assertTrue($isExtra->invoke($service, 'نسبه هالك'));
        $this->assertTrue($isExtra->invoke($service, 'نسبة هالك'));
        $this->assertTrue($isExtra->invoke($service, 'تكلفة العمالة'));
        $this->assertTrue($isExtra->invoke($service, 'كهرباء'));
        $this->assertTrue($isExtra->invoke($service, 'Machine Cost'));
        $this->assertTrue($isExtra->invoke($service, 'Labor'));

        $this->assertFalse($isExtra->invoke($service, 'قماش قطن'));
        $this->assertFalse($isExtra->invoke($service, 'خيط نايلون'));
    }

    public function test_extra_cost_parse_detects_percentage(): void
    {
        $service = app(\App\Services\Items\RecipeSheetImportService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseExtraCostRow');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'نسبة هالك', null, '5', null);
        $this->assertNotNull($result);
        $this->assertEquals('percentage', $result['type']);
        $this->assertEquals(5, $result['value']);

        $result2 = $method->invoke($service, 'سعر المكن', '150', null, null);
        $this->assertNotNull($result2);
        $this->assertEquals('fixed', $result2['type']);
        $this->assertEquals(150, $result2['value']);
    }

    public function test_ignore_label_skips_totals(): void
    {
        $service = app(\App\Services\Items\RecipeSheetImportService::class);

        $reflection = new \ReflectionClass($service);
        $isIgnore = $reflection->getMethod('isIgnoreLabel');
        $isIgnore->setAccessible(true);

        $this->assertTrue($isIgnore->invoke($service, 'الاجمالي'));
        $this->assertTrue($isIgnore->invoke($service, 'الإجمالي'));
        $this->assertTrue($isIgnore->invoke($service, 'اجمالي التكاليف المباشرة'));
        $this->assertFalse($isIgnore->invoke($service, 'سعر المكن'));
    }

    public function test_ingredient_color_stripped_when_not_in_excel_finish_colors(): void
    {
        $service = app(\App\Services\Items\RecipeSheetImportService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeIngredientColorsForRecipe');
        $method->setAccessible(true);

        $recipe = [
            'finish_colors' => ['Black'],
            'ingredients' => [
                ['item_name' => 'قماش', 'color' => 'Black', 'supports_color' => false],
                ['item_name' => 'سوسته', 'color' => 'Maroon', 'supports_color' => false],
                ['item_name' => 'جلد', 'color' => 'Black', 'supports_color' => true],
            ],
        ];

        $method->invokeArgs($service, [&$recipe, true]);

        $this->assertSame('Black', $recipe['ingredients'][0]['color']);
        $this->assertNull($recipe['ingredients'][1]['color']);
        $this->assertNull($recipe['ingredients'][2]['color']);
    }

    public function test_ingredient_colors_cleared_when_sheet_has_no_color_column(): void
    {
        $service = app(\App\Services\Items\RecipeSheetImportService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeIngredientColorsForRecipe');
        $method->setAccessible(true);

        $recipe = [
            'finish_colors' => [],
            'ingredients' => [
                ['item_name' => 'قماش', 'color' => 'Black', 'supports_color' => false],
                ['item_name' => 'سوسته', 'color' => 'Orange', 'supports_color' => false],
            ],
        ];

        $method->invokeArgs($service, [&$recipe, false]);

        $this->assertNull($recipe['ingredients'][0]['color']);
        $this->assertNull($recipe['ingredients'][1]['color']);
    }
}
