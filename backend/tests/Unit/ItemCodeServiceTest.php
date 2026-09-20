<?php

namespace Tests\Unit;

use App\Models\Item;
use App\Models\Measurement;
use App\Models\Production;
use App\Models\Stock;
use App\Services\Items\ItemCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ItemCodeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ItemCodeService $codes;

    private Production $production;

    private Measurement $measurement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codes = app(ItemCodeService::class);
        $this->production = Production::query()->first() ?? Production::create([
            'warehouse' => 'مخزن مواد خام',
            'production_line' => 'خط أكواد أصناف',
        ]);
        $this->measurement = Measurement::query()->first() ?? Measurement::create([
            'unit' => 'قطعة',
            'warehouse' => 'مخزن مواد خام',
        ]);
    }

    public function test_prefixes_match_warehouse_types(): void
    {
        $this->assertSame('10', $this->codes->prefixForWarehouse('مخزن مواد خام'));
        $this->assertSame('20', $this->codes->prefixForWarehouse('مخزن منتج تحت التشغيل'));
        $this->assertSame('30', $this->codes->prefixForWarehouse('مخزن منتج تام'));
        $this->assertSame('40', $this->codes->prefixForWarehouse('مستلزمات تشغيل وأدوات تشغيل'));
        $this->assertSame('50', $this->codes->prefixForWarehouse('مخزن صيانة'));
        $this->assertSame('60', $this->codes->prefixForWarehouse('مخزن تالف'));
        $this->assertSame('90', $this->codes->prefixForWarehouse('مخزن غير معروف'));
    }

    public function test_raw_material_codes_start_with_10_and_increment(): void
    {
        $stock = $this->stockNamed('مخزن مواد خام');
        $first = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $this->assertMatchesRegularExpression('/^10\d+$/', $first);
        $this->assertGreaterThanOrEqual(101001, (int) $first);

        $item = $this->makeItem('مخزن مواد خام', $stock);
        $this->codes->ensureCode($item);
        $this->assertSame($first, $item->item_code);

        $second = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $this->assertSame((string) ((int) $first + 1), $second);
    }

    public function test_finished_goods_use_prefix_30_independently(): void
    {
        $stock = $this->stockNamed('مخزن منتج تام');
        $rawNext = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $finishedNext = $this->codes->nextCodeForWarehouse('مخزن منتج تام');

        $this->assertMatchesRegularExpression('/^30\d+$/', $finishedNext);
        $this->assertNotSame($rawNext, $finishedNext);

        $item = $this->makeItem('مخزن منتج تام', $stock);
        $this->codes->ensureCode($item);
        $this->assertSame($finishedNext, $item->item_code);
    }

    public function test_non_numeric_codes_do_not_affect_sequence(): void
    {
        $stock = $this->stockNamed('مخزن مواد خام');
        Item::query()->create(array_merge($this->baseAttrs('مخزن مواد خام', $stock), [
            'category_name' => 'كود عشوائي '.uniqid(),
            'item_code' => 'ITM-TEST'.strtoupper(substr(uniqid(), -6)),
        ]));

        $next = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $this->assertMatchesRegularExpression('/^10\d+$/', $next);
    }

    public function test_ensure_code_keeps_existing_code(): void
    {
        $stock = $this->stockNamed('مخزن مواد خام');
        $code = 'CUSTOM-KEEP-'.uniqid();
        $item = $this->makeItem('مخزن مواد خام', $stock, $code);
        $this->codes->ensureCode($item);
        $this->assertSame($code, $item->fresh()->item_code);
    }

    public function test_assign_missing_fills_empty_codes_and_skips_existing(): void
    {
        $stock = $this->stockNamed('مخزن مواد خام');
        $keep = 'KEEP-'.uniqid();
        $existing = $this->makeItem('مخزن مواد خام', $stock, $keep);
        $emptyA = $this->makeItem('مخزن مواد خام', $stock, null);
        $emptyB = $this->makeItem('مخزن مواد خام', $stock, null);

        $preview = $this->codes->previewMissing('مخزن مواد خام', [
            (int) $existing->id,
            (int) $emptyA->id,
            (int) $emptyB->id,
        ]);
        $this->assertSame(2, $preview['missing']);

        $first = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $result = $this->codes->assignMissing('مخزن مواد خام', [
            (int) $existing->id,
            (int) $emptyA->id,
            (int) $emptyB->id,
        ]);

        $this->assertSame(2, $result['assigned']);
        $this->assertSame($keep, $existing->fresh()->item_code);
        $this->assertSame($first, $emptyA->fresh()->item_code);
        $this->assertSame((string) ((int) $first + 1), $emptyB->fresh()->item_code);
    }

    public function test_assign_missing_can_replace_codes_that_do_not_match_pattern(): void
    {
        $stock = $this->stockNamed('مخزن مواد خام');
        $pattern = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $matching = $this->makeItem('مخزن مواد خام', $stock, $pattern);
        $custom = $this->makeItem('مخزن مواد خام', $stock, 'ITM-OLD-'.uniqid());

        $preview = $this->codes->previewMissing('مخزن مواد خام', [
            (int) $matching->id,
            (int) $custom->id,
        ]);
        $this->assertSame(0, $preview['missing']);
        $this->assertSame(1, $preview['mismatched']);
        $this->assertSame(1, $preview['matching']);

        $skipped = $this->codes->assignMissing('مخزن مواد خام', [
            (int) $matching->id,
            (int) $custom->id,
        ], false);
        $this->assertSame(0, $skipped['assigned']);
        $this->assertSame('ITM-OLD-', substr((string) $custom->fresh()->item_code, 0, 8));

        $next = $this->codes->nextCodeForWarehouse('مخزن مواد خام');
        $replaced = $this->codes->assignMissing('مخزن مواد خام', [
            (int) $matching->id,
            (int) $custom->id,
        ], true);

        $this->assertSame(1, $replaced['assigned']);
        $this->assertSame(1, $replaced['replaced']);
        $this->assertSame($pattern, $matching->fresh()->item_code);
        $this->assertSame($next, $custom->fresh()->item_code);
    }

    private function stockNamed(string $name): Stock
    {
        return Stock::query()->where('name', $name)->first()
            ?? Stock::create(['name' => $name, 'balance' => 0, 'asset_id' => 0]);
    }

    private function makeItem(string $warehouse, Stock $stock, ?string $itemCode = null): Item
    {
        return Item::query()->create(array_merge($this->baseAttrs($warehouse, $stock), [
            'category_name' => 'صنف كود '.uniqid(),
            'item_code' => $itemCode,
        ]));
    }

    private function baseAttrs(string $warehouse, Stock $stock): array
    {
        return [
            'category_price' => 1,
            'unit_price' => 1,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'warehouse' => $warehouse,
            'production_id' => $this->production->id,
            'measurement_id' => $this->measurement->id,
            'stock_id' => $stock->id,
            'category_image' => '',
        ];
    }
}
