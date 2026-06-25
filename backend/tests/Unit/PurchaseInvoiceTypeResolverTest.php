<?php

namespace Tests\Unit;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseInvoiceKind;
use App\Services\Purchases\PurchaseInvoiceTypeResolver;
use PHPUnit\Framework\TestCase;

class PurchaseInvoiceTypeResolverTest extends TestCase
{
    private PurchaseInvoiceTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PurchaseInvoiceTypeResolver();
    }

    public function test_maps_invoice_types_to_kinds(): void
    {
        $this->assertSame(PurchaseInvoiceKind::PurchaseReceipt, $this->resolver->kind('اضافة وارد جديد'));
        $this->assertSame(PurchaseInvoiceKind::PurchaseReceipt, $this->resolver->kind('تم الاستلام'));
        $this->assertSame(PurchaseInvoiceKind::SalesReturn, $this->resolver->kind('مرتجع مبيعات'));
        $this->assertSame(PurchaseInvoiceKind::Amanat, $this->resolver->kind('امانات'));
        $this->assertSame(PurchaseInvoiceKind::PurchaseReturn, $this->resolver->kind('مرتجع'));
    }

    public function test_purchase_return_is_outbound_with_negative_supplier_delta(): void
    {
        $kind = PurchaseInvoiceKind::PurchaseReturn;

        $this->assertFalse($this->resolver->isInbound($kind));
        $this->assertSame('out', $this->resolver->stockDirection($kind));
        $this->assertSame('PURCHASE_RETURN', $this->resolver->stockTransactionCode($kind));
        $this->assertSame(
            InventoryMovementType::StockDocumentPurchaseReturn,
            $this->resolver->movementType($kind)
        );
        $this->assertSame(-150.0, $this->resolver->supplierBalanceDelta($kind, 150.0, 200.0));
        $this->assertSame(-200.0, $this->resolver->supplierBalanceDelta($kind, 0.0, 200.0));
    }

    public function test_sales_return_does_not_affect_supplier_balance(): void
    {
        $kind = PurchaseInvoiceKind::SalesReturn;

        $this->assertTrue($this->resolver->isInbound($kind));
        $this->assertFalse($this->resolver->affectsSupplierBalance($kind));
        $this->assertSame(0.0, $this->resolver->supplierBalanceDelta($kind, 100.0, 100.0));
    }
}
