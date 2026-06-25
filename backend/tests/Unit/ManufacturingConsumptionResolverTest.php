<?php

namespace Tests\Unit;

use App\Models\Color;
use App\Models\Item;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Stock;
use App\Services\Manufacturing\ManufacturingConsumptionResolver;
use App\Services\Manufacturing\SupportsColorEstimator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManufacturingConsumptionResolverTest extends TestCase
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

    public function test_damour_never_follows_production_color_even_when_flagged(): void
    {
        $this->assertFalse(SupportsColorEstimator::followsProductionColor('دمور', true));
        $this->assertFalse(SupportsColorEstimator::guessFromLabel('دمور'));

        $resolver = app(ManufacturingConsumptionResolver::class);
        $black = $this->makeColor('Black');
        $white = $this->makeColor('White');

        $damourBase = $this->makeItem('دمور', null, true);
        $damourBlack = $this->makeItem('دمور - Black', $damourBase->id, false, $black->id);
        $damourWhite = $this->makeItem('دمور', $damourBase->id, false, $white->id);

        $resolved = $resolver->resolveForProduction($damourBlack, $black->id);

        $this->assertSame((int) $damourBase->id, (int) $resolved->id);
        $this->assertNotSame((int) $damourBlack->id, (int) $resolved->id);
    }

    public function test_fabric_follows_production_color_even_when_flag_is_false(): void
    {
        $this->assertTrue(SupportsColorEstimator::followsProductionColor('قماش سمر ملتون', false));

        $resolver = app(ManufacturingConsumptionResolver::class);
        $grey = $this->makeColor('Grey');
        $black = $this->makeColor('Black');

        $fabricBase = $this->makeItem('قماش سمر ملتون', null, false);
        $fabricGrey = $this->makeItem('قماش سمر ملتون - Grey', $fabricBase->id, false, $grey->id);
        $fabricBlack = $this->makeItem('قماش سمر ملتون - Black', $fabricBase->id, false, $black->id);

        $resolvedFromBase = $resolver->resolveForProduction($fabricBase, $black->id);
        $resolvedFromWrongBomLine = $resolver->resolveForProduction($fabricGrey, $black->id);

        $this->assertSame((int) $fabricBlack->id, (int) $resolvedFromBase->id);
        $this->assertSame((int) $fabricBlack->id, (int) $resolvedFromWrongBomLine->id);
    }

    private function makeColor(string $name): Color
    {
        return Color::query()->firstOrCreate(
            ['name' => $name],
            ['slug' => strtolower(str_replace(' ', '-', $name))]
        );
    }

    private function makeItem(
        string $name,
        ?int $parentId = null,
        bool $supportsColor = false,
        ?int $colorId = null
    ): Item {
        return Item::query()->create([
            'category_name' => $name . '_' . uniqid(),
            'category_price' => 10,
            'unit_price' => 10,
            'initial_balance' => 100,
            'minimum_quantity' => 0,
            'warehouse' => 'مخزن مواد خام',
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $this->rawStock->id,
            'category_image' => '',
            'parent_item_id' => $parentId,
            'supports_color' => $supportsColor,
            'color_id' => $colorId,
            'quantity' => 100,
            'total_price' => 1000,
        ]);
    }
}
