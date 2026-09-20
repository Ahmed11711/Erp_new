<?php

namespace Tests\Unit;

use App\Enums\ProductType;
use App\Models\Item;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Stock;
use App\Services\Manufacturing\RecipeStructureValidator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecipeStructureValidatorTest extends TestCase
{
  private Production $production;
  private Measurement $measurement;
  private Stock $rawStock;

  protected function setUp(): void
  {
    parent::setUp();
    DB::beginTransaction();

    $this->production = Production::first() ?? Production::create([
      'warehouse' => 'مخزن مواد خام',
      'production_line' => 'خط إنتاج افتراضي',
    ]);

    $this->measurement = Measurement::first() ?? Measurement::create([
      'unit' => 'متر',
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

  public function test_allows_finished_warehouse_component_for_different_output_product(): void
  {
    $output = $this->makeItem('Apron', 'مخزن منتج تام', ProductType::Finished->value);
    $fiber = $this->makeItem('فايبر', 'مخزن منتج تام', ProductType::Finished->value);

    $recipe = Recipe::query()->create([
      'recipe_name' => 'Test',
      'output_item_id' => $output->id,
    ]);

    RecipeIngredient::query()->create([
      'recipe_id' => $recipe->id,
      'item_id' => $fiber->id,
      'quantity' => 1,
    ]);

    RecipeStructureValidator::assertValidForRecipe($recipe->fresh(['ingredients.item']), (int) $output->id);

    $this->assertTrue(true);
  }

  public function test_rejects_output_as_its_own_ingredient(): void
  {
    $output = $this->makeItem('فايبر', 'مخزن منتج تام', ProductType::Finished->value);

    $recipe = Recipe::query()->create([
      'recipe_name' => 'Test',
      'output_item_id' => $output->id,
    ]);

    RecipeIngredient::query()->create([
      'recipe_id' => $recipe->id,
      'item_id' => $output->id,
      'quantity' => 1,
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('لا يمكن أن يكون المنتج النهائي مكوّناً');

    RecipeStructureValidator::assertValidForRecipe($recipe->fresh(['ingredients.item']), (int) $output->id);
  }

  public function test_allows_raw_warehouse_variant_even_when_parent_is_finished(): void
  {
    $parent = $this->makeItem('فايبر', 'مخزن منتج تام', ProductType::Finished->value);
    $variant = $this->makeItem('فايبر Grey', 'مخزن مواد خام', ProductType::RawMaterial->value, (int) $parent->id);
    $output = $this->makeItem('Apron', 'مخزن منتج تام', ProductType::Finished->value);

    $recipe = Recipe::query()->create([
      'recipe_name' => 'Test',
      'output_item_id' => $output->id,
    ]);

    RecipeIngredient::query()->create([
      'recipe_id' => $recipe->id,
      'item_id' => $variant->id,
      'quantity' => 1,
    ]);

    RecipeStructureValidator::assertValidForRecipe($recipe->fresh(['ingredients.item']), (int) $output->id);

    $this->assertTrue(true);
  }

  private function makeItem(
    string $name,
    string $warehouse,
    string $productType,
    ?int $parentId = null,
  ): Item {
    $stock = Stock::where('name', $warehouse)->first()
      ?? Stock::create(['name' => $warehouse, 'balance' => 0, 'asset_id' => 0]);

    return Item::query()->create([
      'category_name' => $name . '_' . uniqid(),
      'category_price' => 10,
      'unit_price' => 10,
      'initial_balance' => 100,
      'minimum_quantity' => 0,
      'warehouse' => $warehouse,
      'product_type' => $productType,
      'production_id' => $this->production->id,
      'measurement_id' => $this->measurement->id,
      'stock_id' => $stock->id,
      'category_image' => '',
      'parent_item_id' => $parentId,
      'quantity' => 100,
      'total_price' => 1000,
    ]);
  }
}
