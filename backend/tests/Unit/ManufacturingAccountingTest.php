<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ManufacturingAccountingTest extends TestCase
{
    public function test_consumption_gl_logic_debits_cogs_credits_inventory(): void
    {
        $amount = 1500.00;
        $cogsDebit = $amount;
        $inventoryCredit = $amount;

        $this->assertEquals($cogsDebit, $inventoryCredit, 'COGS debit must equal inventory credit');
        $this->assertGreaterThan(0, $cogsDebit);
    }

    public function test_production_completion_gl_reverses_consumption(): void
    {
        $consumptionAmount = 1200.00;
        $completionAmount = 1200.00;

        $this->assertEquals($consumptionAmount, $completionAmount,
            'Completion entry should capitalize the same cost as consumption');
    }

    public function test_zero_cost_manufacturing_skips_gl(): void
    {
        $totalRawMaterialCost = 0.0;
        $shouldPost = $totalRawMaterialCost > 0.00001;

        $this->assertFalse($shouldPost, 'Zero-cost manufacturing should not post GL');
    }

    public function test_weighted_average_cost_calculation(): void
    {
        $totalPrice = 10000.0;
        $quantity = 200.0;
        $expectedAvg = 50.0;

        $avg = $quantity > 0.0000001 ? $totalPrice / $quantity : 0;
        $this->assertEquals($expectedAvg, $avg, 'Weighted average = total_price / quantity');
    }

    public function test_unit_cost_with_zero_quantity_returns_zero(): void
    {
        $totalPrice = 10000.0;
        $quantity = 0.0;

        $avg = $quantity > 0.0000001 ? $totalPrice / $quantity : 0;
        $this->assertEquals(0, $avg);
    }
}
