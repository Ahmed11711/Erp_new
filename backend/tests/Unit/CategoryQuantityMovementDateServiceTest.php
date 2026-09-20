<?php

namespace Tests\Unit;

use App\Services\Items\CategoryQuantityMovementDateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class CategoryQuantityMovementDateServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryQuantityMovementDateService $dates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dates = app(CategoryQuantityMovementDateService::class);
        Carbon::setTestNow(Carbon::parse('2026-08-20 15:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_date_uses_now(): void
    {
        $parsed = $this->dates->parse(null);
        $this->assertTrue($parsed->equalTo(now()));
    }

    public function test_today_keeps_current_time(): void
    {
        $parsed = $this->dates->parse('2026-08-20');
        $this->assertTrue($parsed->equalTo(now()));
    }

    public function test_past_date_is_noon_on_that_day(): void
    {
        $parsed = $this->dates->parse('2026-01-15');
        $this->assertSame('2026-01-15 12:00:00', $parsed->format('Y-m-d H:i:s'));
    }

    public function test_future_date_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->dates->parse('2026-12-31');
    }

    public function test_recalculate_running_balances_follows_created_at_not_id(): void
    {
        $category = \App\Models\Category::query()->create([
            'category_name' => 'qty-date-test-'.uniqid(),
            'warehouse' => 'مخزن مواد خام',
            'category_price' => 1,
            'unit_price' => 1,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'category_image' => '',
            'quantity' => 60,
        ]);
        $categoryId = (int) $category->id;

        DB::table('categories_balance')->insert([
            [
                'invoice_number' => 'A',
                'category_id' => $categoryId,
                'type' => 'رصيد',
                'quantity' => 50,
                'balance_before' => 0,
                'balance_after' => 50,
                'price' => 1,
                'total_price' => 50,
                'by' => 'test',
                'created_at' => '2026-01-01 12:00:00',
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'B',
                'category_id' => $categoryId,
                'type' => 'شحن طلب',
                'quantity' => -10,
                'balance_before' => 50,
                'balance_after' => 40,
                'price' => 1,
                'total_price' => -10,
                'by' => 'test',
                'created_at' => '2026-02-01 12:00:00',
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'C',
                'category_id' => $categoryId,
                'type' => 'تعديل الصنف',
                'quantity' => 20,
                'balance_before' => 40,
                'balance_after' => 60,
                'price' => 1,
                'total_price' => 20,
                'by' => 'test',
                'created_at' => '2026-01-15 12:00:00',
                'updated_at' => now(),
            ],
        ]);

        $this->dates->recalculateRunningBalances($categoryId, 60.0);

        $rows = DB::table('categories_balance')
            ->where('category_id', $categoryId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['invoice_number', 'balance_before', 'balance_after']);

        $byRef = [];
        foreach ($rows as $row) {
            $byRef[$row->invoice_number] = $row;
        }

        $this->assertEquals(0.0, (float) $byRef['A']->balance_before);
        $this->assertEquals(50.0, (float) $byRef['A']->balance_after);
        $this->assertEquals(50.0, (float) $byRef['C']->balance_before);
        $this->assertEquals(70.0, (float) $byRef['C']->balance_after);
        $this->assertEquals(70.0, (float) $byRef['B']->balance_before);
        $this->assertEquals(60.0, (float) $byRef['B']->balance_after);
    }

    public function test_quantity_as_of_excludes_later_dated_adjustments(): void
    {
        $category = \App\Models\Category::query()->create([
            'category_name' => 'qty-asof-test-'.uniqid(),
            'warehouse' => 'مخزن مواد خام',
            'category_price' => 1,
            'unit_price' => 1,
            'initial_balance' => 0,
            'minimum_quantity' => 0,
            'category_image' => '',
            'quantity' => 60,
        ]);
        $categoryId = (int) $category->id;

        DB::table('categories_balance')->insert([
            [
                'invoice_number' => 'A',
                'category_id' => $categoryId,
                'type' => 'رصيد',
                'quantity' => 50,
                'balance_before' => 0,
                'balance_after' => 50,
                'price' => 1,
                'total_price' => 50,
                'by' => 'test',
                'created_at' => '2026-01-01 12:00:00',
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'C',
                'category_id' => $categoryId,
                'type' => 'تعديل الصنف',
                'quantity' => 20,
                'balance_before' => 50,
                'balance_after' => 70,
                'price' => 1,
                'total_price' => 20,
                'by' => 'test',
                'created_at' => '2026-01-15 12:00:00',
                'updated_at' => now(),
            ],
            [
                'invoice_number' => 'B',
                'category_id' => $categoryId,
                'type' => 'شحن طلب',
                'quantity' => -10,
                'balance_before' => 70,
                'balance_after' => 60,
                'price' => 1,
                'total_price' => -10,
                'by' => 'test',
                'created_at' => '2026-02-01 12:00:00',
                'updated_at' => now(),
            ],
        ]);

        $this->assertEquals(50.0, $this->dates->quantityAsOf($categoryId, 60.0, '2026-01-10 23:59:59'));
        $this->assertEquals(70.0, $this->dates->quantityAsOf($categoryId, 60.0, '2026-01-31 23:59:59'));
        $this->assertEquals(60.0, $this->dates->quantityAsOf($categoryId, 60.0, '2026-02-28 23:59:59'));
    }
}
