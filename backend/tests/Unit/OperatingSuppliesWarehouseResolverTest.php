<?php

namespace Tests\Unit;

use App\Services\Inventory\OperatingSuppliesWarehouseResolver;
use PHPUnit\Framework\TestCase;

class OperatingSuppliesWarehouseResolverTest extends TestCase
{
    public function test_warehouse_and_account_constants(): void
    {
        $this->assertSame('مستلزمات تشغيل وأدوات تشغيل', OperatingSuppliesWarehouseResolver::WAREHOUSE_NAME);
        $this->assertSame('مخزون مستلزمات التشغيل', OperatingSuppliesWarehouseResolver::INVENTORY_ACCOUNT_NAME);
        $this->assertSame('مصروفات صناعية غير مباشرة - مستلزمات تشغيل', OperatingSuppliesWarehouseResolver::OVERHEAD_ACCOUNT_NAME);
        $this->assertSame('1000225', OperatingSuppliesWarehouseResolver::INVENTORY_CODE);
        $this->assertSame('500017', OperatingSuppliesWarehouseResolver::OVERHEAD_CODE);
        $this->assertSame('operating_supplies', OperatingSuppliesWarehouseResolver::WAREHOUSE_TYPE);
    }

    public function test_is_operating_supplies_warehouse_by_name(): void
    {
        $this->assertTrue(OperatingSuppliesWarehouseResolver::isOperatingSuppliesWarehouse(
            null,
            OperatingSuppliesWarehouseResolver::WAREHOUSE_NAME
        ));
        $this->assertFalse(OperatingSuppliesWarehouseResolver::isOperatingSuppliesWarehouse(
            null,
            'مخزن مواد خام'
        ));
    }
}
