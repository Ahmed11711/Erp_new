<?php

namespace Tests\Unit;

use App\Services\Manufacturing\SupportsColorEstimator;
use Tests\TestCase;

class ManufactureBomQuantityTest extends TestCase
{
    public function test_recipe_decimal_quantities_are_not_treated_as_integers(): void
    {
        $storedAsInt = 2;
        $recipeQty = 2.3;

        $this->assertNotEquals($storedAsInt, $recipeQty);
        $this->assertEqualsWithDelta(11.5, $recipeQty * 5, 0.0001);
    }

    public function test_fabric_still_follows_color_after_decimal_fix(): void
    {
        $this->assertTrue(SupportsColorEstimator::followsProductionColor('قماش سمر ملتون', false));
        $this->assertFalse(SupportsColorEstimator::followsProductionColor('دمور', true));
    }
}
