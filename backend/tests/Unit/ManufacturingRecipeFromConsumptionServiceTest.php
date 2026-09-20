<?php

namespace Tests\Unit;

use App\Services\Manufacturing\ManufacturingRecipeFromConsumptionService;
use Tests\TestCase;

class ManufacturingRecipeFromConsumptionServiceTest extends TestCase
{
    public function test_build_recipe_products_from_consumption_lines(): void
    {
        $service = app(ManufacturingRecipeFromConsumptionService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildRecipeProducts');
        $method->setAccessible(true);

        $lines = [[
            'resolved_category_id' => 200,
            'quantity' => 4.0,
            'unit_cost' => 2.5,
        ]];

        $products = $method->invoke($service, $lines, 2.0);

        $this->assertCount(1, $products);
        $this->assertSame(200, $products[0]['id']);
        $this->assertSame(2.0, (float) $products[0]['quantity']);
        $this->assertSame(5.0, (float) $products[0]['total_price']);
    }
}
