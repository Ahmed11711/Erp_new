<?php

namespace Tests\Unit;

use App\Services\Items\CostCalculationService;
use PHPUnit\Framework\TestCase;

class CostCalculationServiceTest extends TestCase
{
    private CostCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CostCalculationService();
    }

    // ──────────────────────────────────────────────────────────
    // materialsCost
    // ──────────────────────────────────────────────────────────

    public function test_materials_cost_with_simple_ingredients(): void
    {
        $ingredients = [
            ['quantity' => '10', 'unit_cost' => '5.5'],
            ['quantity' => '3',  'unit_cost' => '12'],
        ];

        $result = $this->service->materialsCost($ingredients);

        // 10 * 5.5 + 3 * 12 = 55 + 36 = 91
        $this->assertSame('91.0000', $result);
    }

    public function test_materials_cost_with_fractional_quantities(): void
    {
        $ingredients = [
            ['quantity' => '2.5',   'unit_cost' => '10.3333'],
            ['quantity' => '0.125', 'unit_cost' => '100'],
        ];

        // 2.5 * 10.3333 = 25.8332(50) → 25.8332 (scale 4)
        // 0.125 * 100   = 12.5000
        // Total          = 38.3332
        $result = $this->service->materialsCost($ingredients);
        $this->assertSame('38.3332', $result);
    }

    public function test_materials_cost_skips_null_unit_cost(): void
    {
        $ingredients = [
            ['quantity' => '5', 'unit_cost' => '10'],
            ['quantity' => '3', 'unit_cost' => null],
            ['quantity' => '2', 'unit_cost' => ''],
        ];

        $result = $this->service->materialsCost($ingredients);
        $this->assertSame('50.0000', $result);
    }

    public function test_materials_cost_empty_list(): void
    {
        $result = $this->service->materialsCost([]);
        $this->assertSame('0', $result);
    }

    public function test_materials_cost_large_dataset(): void
    {
        $ingredients = [];
        for ($i = 0; $i < 500; $i++) {
            $ingredients[] = ['quantity' => '1.000001', 'unit_cost' => '0.0001'];
        }

        $result = $this->service->materialsCost($ingredients);
        // 500 * 1.000001 * 0.0001 = 500 * 0.0001000 = 0.0500 (close)
        $this->assertTrue(bccomp($result, '0', 4) > 0);
    }

    // ──────────────────────────────────────────────────────────
    // Extra costs — fixed
    // ──────────────────────────────────────────────────────────

    public function test_total_fixed_costs(): void
    {
        $extras = [
            ['type' => 'fixed',      'value' => '150'],
            ['type' => 'percentage', 'value' => '10'],
            ['type' => 'fixed',      'value' => '25.5'],
        ];

        $result = $this->service->totalFixedCosts($extras);
        $this->assertSame('175.5000', $result);
    }

    public function test_total_fixed_costs_empty(): void
    {
        $result = $this->service->totalFixedCosts([]);
        $this->assertSame('0', $result);
    }

    // ──────────────────────────────────────────────────────────
    // Extra costs — percentage
    // ──────────────────────────────────────────────────────────

    public function test_total_percentage_costs(): void
    {
        $extras = [
            ['type' => 'percentage', 'value' => '10'],
            ['type' => 'fixed',      'value' => '999'],
            ['type' => 'percentage', 'value' => '5'],
        ];

        // matCost = 200, 10% = 20, 5% = 10 → 30
        $result = $this->service->totalPercentageCosts($extras, '200');
        $this->assertSame('30.0000', $result);
    }

    public function test_percentage_on_zero_materials(): void
    {
        $extras = [['type' => 'percentage', 'value' => '25']];

        $result = $this->service->totalPercentageCosts($extras, '0');
        $this->assertSame('0.0000', $result);
    }

    // ──────────────────────────────────────────────────────────
    // calculateFinalCost
    // ──────────────────────────────────────────────────────────

    public function test_final_cost_with_all_components(): void
    {
        $ingredients = [
            ['quantity' => '10', 'unit_cost' => '5'],     // 50
            ['quantity' => '20', 'unit_cost' => '2.5'],   // 50
        ];
        // materials = 100

        $extras = [
            ['type' => 'fixed',      'value' => '30'],    // +30
            ['type' => 'percentage', 'value' => '10'],    // +10 (10% of 100)
            ['type' => 'fixed',      'value' => '5'],     // +5
            ['type' => 'percentage', 'value' => '5'],     // +5  (5% of 100)
        ];
        // final = 100 + 35 + 15 = 150

        $result = $this->service->calculateFinalCost($ingredients, $extras);

        $this->assertSame('100.0000', $result['materials_cost']);
        $this->assertSame('35.0000',  $result['fixed_costs']);
        $this->assertSame('15.0000',  $result['percentage_costs']);
        $this->assertSame('150.0000', $result['final_cost']);
    }

    public function test_final_cost_no_extras(): void
    {
        $ingredients = [
            ['quantity' => '3', 'unit_cost' => '7'],
        ];

        $result = $this->service->calculateFinalCost($ingredients, []);

        $this->assertSame('21.0000', $result['materials_cost']);
        $this->assertSame('0',       $result['fixed_costs']);
        $this->assertSame('0',       $result['percentage_costs']);
        $this->assertSame('21.0000', $result['final_cost']);
    }

    // ──────────────────────────────────────────────────────────
    // Margin
    // ──────────────────────────────────────────────────────────

    public function test_margin_percent_normal(): void
    {
        // margin = (200 - 150) / 200 = 0.25
        $result = $this->service->marginPercent('200', '150');
        $this->assertSame('0.250000', $result);
    }

    public function test_margin_percent_zero_selling_price(): void
    {
        $result = $this->service->marginPercent('0', '100');
        $this->assertNull($result);
    }

    public function test_margin_percent_negative_margin(): void
    {
        // selling 80, cost 100 → margin = (80-100)/80 = -0.25
        $result = $this->service->marginPercent('80', '100');
        $this->assertTrue(bccomp($result, '0', 4) < 0, 'Margin should be negative when cost exceeds price.');
    }

    public function test_margin_percent_exact_breakeven(): void
    {
        $result = $this->service->marginPercent('100', '100');
        $this->assertSame('0.000000', $result);
    }

    // ──────────────────────────────────────────────────────────
    // Floating-point precision (Task 5)
    // ──────────────────────────────────────────────────────────

    public function test_floating_point_precision(): void
    {
        // 0.1 + 0.2 == 0.3 should hold true with bcmath
        $ingredients = [
            ['quantity' => '0.1', 'unit_cost' => '1'],
            ['quantity' => '0.2', 'unit_cost' => '1'],
        ];

        $result = $this->service->materialsCost($ingredients);
        $this->assertSame('0.3000', $result);
    }

    public function test_high_precision_no_drift(): void
    {
        // 1/3 * 3 should = 1 with proper bcmath handling
        $ingredients = [
            ['quantity' => '0.333333', 'unit_cost' => '3'],
        ];
        $result = $this->service->materialsCost($ingredients);
        // 0.333333 * 3 = 0.999999 (exactly, not 1.0 — bc truncates)
        $this->assertSame('0.9999', $result);
    }

    // ──────────────────────────────────────────────────────────
    // Validation helpers
    // ──────────────────────────────────────────────────────────

    public function test_validate_ingredients_clean_data(): void
    {
        $ingredients = [
            ['item_id' => 1, 'quantity' => '5',  'unit_cost' => '10'],
            ['item_id' => 2, 'quantity' => '3',  'unit_cost' => '8'],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertEmpty($errors);
    }

    public function test_validate_ingredients_negative_quantity(): void
    {
        $ingredients = [
            ['item_id' => 1, 'quantity' => '-5', 'unit_cost' => '10'],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('negative quantity', $errors[0]);
    }

    public function test_validate_ingredients_zero_quantity(): void
    {
        $ingredients = [
            ['item_id' => 1, 'quantity' => '0', 'unit_cost' => '10'],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('zero quantity', $errors[0]);
    }

    public function test_validate_ingredients_missing_unit_cost(): void
    {
        $ingredients = [
            ['item_id' => 1, 'quantity' => '5', 'unit_cost' => null],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('missing unit cost', $errors[0]);
    }

    public function test_validate_ingredients_missing_item_id(): void
    {
        $ingredients = [
            ['item_id' => null, 'quantity' => '5', 'unit_cost' => '10'],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('missing material reference', $errors[0]);
    }

    public function test_validate_ingredients_duplicate_materials(): void
    {
        $ingredients = [
            ['item_id' => 1, 'quantity' => '5',  'unit_cost' => '10'],
            ['item_id' => 2, 'quantity' => '3',  'unit_cost' => '8'],
            ['item_id' => 1, 'quantity' => '2',  'unit_cost' => '10'],
        ];

        $errors = $this->service->validateIngredients($ingredients);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate material', $errors[0]);
    }

    public function test_validate_cost_integrity_negative_final_cost(): void
    {
        $breakdown = [
            'final_cost'     => '-5.0000',
            'margin_percent' => null,
        ];

        $errors = $this->service->validateCostIntegrity($breakdown);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('negative', $errors[0]);
    }

    public function test_validate_cost_integrity_negative_margin(): void
    {
        $breakdown = [
            'final_cost'     => '150.0000',
            'margin_percent' => '-0.250000',
        ];

        $errors = $this->service->validateCostIntegrity($breakdown);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Negative margin', $errors[0]);
    }

    public function test_validate_cost_integrity_healthy(): void
    {
        $breakdown = [
            'final_cost'     => '100.0000',
            'margin_percent' => '0.250000',
        ];

        $errors = $this->service->validateCostIntegrity($breakdown);
        $this->assertEmpty($errors);
    }
}
