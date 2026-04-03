<?php

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\InventoryGlPostingService;
use Tests\TestCase;

class InventoryGlPostingServiceTest extends TestCase
{
    public function test_post_purchase_receipt_skips_when_amount_is_zero(): void
    {
        $accounting = $this->createMock(AccountingService::class);
        $accounting->expects($this->never())->method('updateAccountHierarchyBalances');

        $service = new InventoryGlPostingService($accounting);
        $supplier = new Supplier(['supplier_name' => 'Test']);

        $service->postPurchaseReceipt(0, $supplier, 'وصف تجريبي');

        $this->assertTrue(true);
    }
}
