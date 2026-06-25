<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Production;
use App\Models\Measurement;
use App\Models\Stock;
use App\Services\Manufacturing\ManufacturingConsumptionPlannerService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManufacturingConsumptionPlannerServiceTest extends TestCase
{
    public function test_apply_overrides_updates_quantities_and_flags(): void
    {
        $service = app(ManufacturingConsumptionPlannerService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyOverrides');
        $method->setAccessible(true);

        $base = [[
            'bom_item_id' => 10,
            'resolved_category_id' => 20,
            'default_resolved_category_id' => 20,
            'default_item_name' => 'قماش',
            'item_name' => 'قماش',
            'default_quantity' => 5.0,
            'quantity' => 5.0,
            'unit_cost' => 2.0,
            'line_cost' => 10.0,
            'available_quantity' => 100.0,
            'sufficient' => true,
            'is_customized' => false,
            'is_substituted' => false,
        ]];

        $result = $method->invoke($service, $base, [[
            'bom_item_id' => 10,
            'resolved_category_id' => 20,
            'quantity' => 3,
        ]]);

        $this->assertCount(1, $result);
        $this->assertSame(3.0, (float) $result[0]['quantity']);
        $this->assertSame(6.0, (float) $result[0]['line_cost']);
        $this->assertTrue($result[0]['is_customized']);
    }

    public function test_apply_overrides_allows_material_substitution_by_bom_line(): void
    {
        DB::beginTransaction();

        $production = Production::first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'test',
        ]);
        $measurement = Measurement::first() ?? Measurement::create([
            'unit' => 'متر',
            'warehouse' => 'مخزن مواد خام',
        ]);
        $stock = Stock::where('name', 'مخزن مواد خام')->first()
            ?? Stock::create(['name' => 'مخزن مواد خام', 'balance' => 0, 'asset_id' => 0]);

        $substitute = Category::create([
            'category_name' => 'دامور بفته_' . uniqid(),
            'category_price' => 5,
            'unit_price' => 5,
            'initial_balance' => 50,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'production_id' => $production->id,
            'measurement_id' => $measurement->id,
            'stock_id' => $stock->id,
            'category_image' => '',
        ]);
        $substitute->quantity = 50;
        $substitute->total_price = 250;
        $substitute->save();

        $service = app(ManufacturingConsumptionPlannerService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('applyOverrides');
        $method->setAccessible(true);

        $base = [[
            'bom_item_id' => 10,
            'resolved_category_id' => 20,
            'default_resolved_category_id' => 20,
            'default_item_name' => 'دمور',
            'item_name' => 'دمور',
            'warehouse' => 'مخزن مواد خام',
            'default_quantity' => 2.0,
            'quantity' => 2.0,
            'unit_cost' => 3.0,
            'line_cost' => 6.0,
            'available_quantity' => 10.0,
            'sufficient' => true,
            'is_customized' => false,
            'is_substituted' => false,
        ]];

        $result = $method->invoke($service, $base, [[
            'bom_item_id' => 10,
            'resolved_category_id' => (int) $substitute->id,
            'quantity' => 2,
        ]]);

        DB::rollBack();

        $this->assertSame((int) $substitute->id, (int) $result[0]['resolved_category_id']);
        $this->assertTrue($result[0]['is_substituted']);
        $this->assertSame(50.0, (float) $result[0]['available_quantity']);
    }
}
