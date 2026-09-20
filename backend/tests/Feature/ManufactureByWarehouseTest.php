<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManufactureByWarehouseTest extends TestCase
{
    private User $admin;
    private Production $production;
    private Measurement $measurement;
    private Stock $finishedStock;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->admin = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن منتج تام',
            'production_line' => 'test',
        ]);
        $this->measurement = Measurement::where('warehouse', 'مخزن منتج تام')->first()
            ?? Measurement::create([
                'unit' => 'وحده',
                'warehouse' => 'مخزن منتج تام',
            ]);
        $this->finishedStock = Stock::where('name', 'مخزن منتج تام')->first()
            ?? Stock::create(['name' => 'مخزن منتج تام', 'balance' => 0, 'asset_id' => 0]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_manufacture_only_lists_recipe_bases_not_color_variants_without_own_recipe(): void
    {
        $base = $this->makeFinished('Base Table');
        $variant = $this->makeFinished('Base Table - Black Marble', [
            'parent_item_id' => $base->id,
        ]);

        Manufacture::create([
            'product_id' => $base->id,
            'total' => 150,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/manfucture_by_warhouse?warehouse='.urlencode('مخزن منتج تام'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $base->id, $ids);
        $this->assertNotContains((int) $variant->id, $ids);
    }

    public function test_manufacture_only_lists_color_variants_that_own_a_recipe(): void
    {
        $base = $this->makeFinished('Bolar Base');
        $variant = $this->makeFinished('Bolar - Beige Variant', [
            'parent_item_id' => $base->id,
        ]);

        $raw = $this->makeFinished('Raw Beige Bom');
        $raw->warehouse = 'مخزن مواد خام';
        $raw->product_type = 'raw';
        $raw->save();

        $recipe = \App\Models\Recipe::create([
            'recipe_name' => 'Bolar - Beige Variant',
            'output_item_id' => $variant->id,
        ]);
        \App\Models\RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id' => $raw->id,
            'quantity' => 1,
            'unit_cost' => 5,
        ]);
        $variant->recipe_id = $recipe->id;
        $variant->save();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/manfucture_by_warhouse?warehouse='.urlencode('مخزن منتج تام'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $variant->id, $ids);
    }

    public function test_manufacture_only_lists_products_with_recipe_even_without_manufacture_row(): void
    {
        $raw = $this->makeFinished('Raw For Recipe List');
        $raw->warehouse = 'مخزن مواد خام';
        $raw->product_type = 'raw';
        $raw->save();

        $finished = $this->makeFinished('Finished With Recipe Only');

        $recipe = \App\Models\Recipe::create([
            'recipe_name' => 'Recipe for '.$finished->category_name,
            'output_item_id' => $finished->id,
        ]);
        \App\Models\RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'item_id' => $raw->id,
            'quantity' => 2,
            'unit_cost' => 10,
        ]);
        $finished->recipe_id = $recipe->id;
        $finished->save();

        $this->assertFalse(
            Manufacture::where('product_id', $finished->id)->exists(),
            'Fixture must not have a legacy manufacture row'
        );

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/manfucture_by_warhouse?warehouse='.urlencode('مخزن منتج تام'));

        $response->assertOk();
        $rows = collect($response->json());
        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $finished->id, $ids);
        $match = $rows->firstWhere('id', $finished->id);
        $this->assertEquals(20.0, (float) $match['cost']);
    }

    public function test_all_categories_scope_excludes_color_variants(): void
    {
        $base = $this->makeFinished('Merge Base');
        $variant = $this->makeFinished('Merge Base - White', [
            'parent_item_id' => $base->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/manfucture_by_warhouse?warehouse='
                .urlencode('مخزن منتج تام').'&scope=all_categories');

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $base->id, $ids);
        $this->assertNotContains((int) $variant->id, $ids);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeFinished(string $name, array $extra = []): Category
    {
        $item = Category::create(array_merge([
            'category_name' => $name.'_'.uniqid(),
            'category_price' => 100,
            'unit_price' => 80,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن منتج تام',
            'product_type' => 'finished',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->finishedStock->id,
            'category_image' => '',
        ], $extra));
        $item->quantity = 0;
        $item->save();

        return $item;
    }
}
