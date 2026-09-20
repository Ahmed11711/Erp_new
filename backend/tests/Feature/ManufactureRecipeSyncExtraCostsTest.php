<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Manufacture;
use App\Models\ManufactureProduct;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeExtraCost;
use App\Models\Stock;
use App\Models\User;
use App\Services\Manufacturing\ManufactureRecipeSyncService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ensures POST /manufacture sync persists recipe_extra_costs alongside Recipe/BOM rows.
 */
class ManufactureRecipeSyncExtraCostsTest extends TestCase
{
    private Production $production;

    private Measurement $measurementRaw;

    private Measurement $measurementFinished;

    private Stock $rawStock;

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

        $this->measurementRaw = Measurement::first() ?? Measurement::create([
            'unit'      => 'متر',
            'warehouse' => 'مخزن مواد خام',
        ]);

        $this->measurementFinished = Measurement::where('warehouse', 'مخزن منتج تام')->first()
            ?? Measurement::create([
                'unit'      => 'قطعة',
                'warehouse' => 'مخزن منتج تام',
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

    private function makeRaw(string $suffix): Category
    {
        $c = Category::create([
            'category_name'    => 'raw_'.$suffix.'_'.uniqid(),
            'category_price'   => 5,
            'unit_price'       => 5,
            'initial_balance'  => 100,
            'minimum_quantity' => 0,
            'warehouse'        => 'مخزن مواد خام',
            'production_id'    => $this->production->id,
            'measurement_id'   => $this->measurementRaw->id,
            'stock_id'         => $this->rawStock->id,
            'category_image'   => '',
        ]);
        $c->quantity = 100;
        $c->save();

        return $c;
    }

    private function makeFinished(string $suffix): Category
    {
        $c = Category::create([
            'category_name'    => 'fg_'.$suffix.'_'.uniqid(),
            'category_price'   => 100,
            'unit_price'       => 100,
            'sell_total_price' => 0,
            'initial_balance'  => 0,
            'minimum_quantity' => 0,
            'warehouse'        => 'مخزن منتج تام',
            'production_id'    => $this->production->id,
            'measurement_id'   => $this->measurementFinished->id,
            'stock_id'         => $this->finishedStock->id,
            'category_image'   => '',
        ]);
        $c->quantity = 0;
        $c->save();

        return $c;
    }

    public function test_manufacture_store_persists_extra_costs_on_recipe(): void
    {
        $raw = $this->makeRaw('ec');
        $fg = $this->makeFinished('ec');

        $payload = [
            'product_id' => $fg->id,
            'total'      => 50,
            'products'   => [
                [
                    'id'           => $raw->id,
                    'quantity'     => 4,
                    'total_price'  => 40,
                ],
            ],
            'extra_costs' => [
                ['name' => 'ماكينة', 'type' => 'fixed', 'value' => 10],
                ['name' => 'هالك', 'type' => 'percentage', 'value' => 5],
            ],
        ];

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/manufacture', $payload);

        $response->assertCreated();

        $recipe = Recipe::query()->where('output_item_id', $fg->id)->first();
        $this->assertNotNull($recipe);
        $this->assertCount(2, $recipe->fresh('extraCosts')->extraCosts);

        $this->assertDatabaseHas('recipe_extra_costs', [
            'recipe_id' => $recipe->id,
            'name'      => 'ماكينة',
            'type'      => 'fixed',
        ]);
    }

    public function test_delete_recipe_removes_manufacture_row_for_bom_list(): void
    {
        $raw = $this->makeRaw('bomdel');
        $fg = $this->makeFinished('bomdel');

        $this->actingAs($this->admin, 'api')->postJson('/api/manufacture', [
            'product_id' => $fg->id,
            'total'      => 40,
            'products'   => [
                ['id' => $raw->id, 'quantity' => 8, 'total_price' => 40],
            ],
        ])->assertCreated();

        $recipe = Recipe::query()->where('output_item_id', $fg->id)->firstOrFail();

        $mId = Manufacture::query()->where('product_id', $fg->id)->value('id');
        $this->assertNotNull($mId);
        $this->assertGreaterThan(0, ManufactureProduct::query()->where('manufacture_id', $mId)->count());

        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/recipes/{$recipe->id}")
            ->assertOk();

        $this->assertDatabaseMissing('manufactures', ['id' => $mId]);
        $this->assertDatabaseMissing('manufacture_products', ['manufacture_id' => $mId]);
    }

    public function test_sync_service_accept_object_style_extra_rows(): void
    {
        // Some clients may send stdClass-decoded lines; normalize safely.
        $raw = $this->makeRaw('obj');
        $fg = $this->makeFinished('obj');

        $svc = app(ManufactureRecipeSyncService::class);
        $extra = json_decode(json_encode([
            ['name' => 'Labor', 'type' => 'fixed', 'value' => 3],
        ]), false);

        $svc->sync((int) $fg->id, [
            ['id' => $raw->id, 'quantity' => 1, 'total_price' => 5],
        ], $extra);

        $recipe = Recipe::query()->where('output_item_id', $fg->id)->first();
        $this->assertNotNull($recipe);
        $this->assertSame(1, RecipeExtraCost::query()->where('recipe_id', $recipe->id)->count());
    }
}
