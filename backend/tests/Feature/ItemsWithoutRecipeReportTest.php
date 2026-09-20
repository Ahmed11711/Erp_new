<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemsWithoutRecipeReportTest extends TestCase
{
    private Production $production;

    private Measurement $measurementFinished;

    private Stock $finishedStock;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->admin = User::where('department', 'Admin')->first()
            ?? User::factory()->create(['department' => 'Admin']);

        $this->production = Production::first() ?? Production::create([
            'warehouse'       => 'مخزن مواد خام',
            'production_line' => 'خط إنتاج افتراضي',
        ]);

        $this->measurementFinished = Measurement::where('warehouse', 'مخزن منتج تام')->first()
            ?? Measurement::create([
                'unit'      => 'قطعة',
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

    private function makeFinished(string $suffix, array $extra = []): Category
    {
        $c = Category::create(array_merge([
            'category_name'    => 'fg_'.$suffix.'_'.uniqid(),
            'category_price'   => 100,
            'unit_price'       => 100,
            'sell_total_price' => 0,
            'initial_balance'  => 0,
            'minimum_quantity' => 0,
            'warehouse'        => 'مخزن منتج تام',
            'product_type'     => 'finished',
            'production_id'    => $this->production->id,
            'measurement_id'   => $this->measurementFinished->id,
            'stock_id'         => $this->finishedStock->id,
            'category_image'   => '',
        ], $extra));
        $c->quantity = 0;
        $c->save();

        return $c;
    }

    public function test_lists_finished_items_without_recipe(): void
    {
        $missing = $this->makeFinished('missing');

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/items-without-recipes');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($missing->id, $ids);
    }

    public function test_excludes_items_with_manufacture_or_recipe(): void
    {
        $withManufacture = $this->makeFinished('has_mfg');
        Manufacture::create([
            'product_id' => $withManufacture->id,
            'total'      => 10,
        ]);

        $withRecipeOutput = $this->makeFinished('has_recipe');
        Recipe::create([
            'recipe_name'    => 'recipe_'.$withRecipeOutput->id,
            'output_item_id' => $withRecipeOutput->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/items-without-recipes');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($withManufacture->id, $ids);
        $this->assertNotContains($withRecipeOutput->id, $ids);
    }

    public function test_excludes_color_variants(): void
    {
        $base = $this->makeFinished('base');
        $variant = $this->makeFinished('variant', [
            'parent_item_id' => $base->id,
            'category_name'  => 'fg_variant_'.uniqid(),
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/manufacture/items-without-recipes');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($base->id, $ids);
        $this->assertNotContains($variant->id, $ids);
    }
}
