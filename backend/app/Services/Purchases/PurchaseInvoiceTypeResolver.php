<?php

namespace App\Services\Purchases;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseInvoiceKind;

class PurchaseInvoiceTypeResolver
{
    public function kind(?string $invoiceType): PurchaseInvoiceKind
    {
        return match (trim((string) $invoiceType)) {
            'مرتجع مبيعات' => PurchaseInvoiceKind::SalesReturn,
            'امانات' => PurchaseInvoiceKind::Amanat,
            'مرتجع' => PurchaseInvoiceKind::PurchaseReturn,
            default => PurchaseInvoiceKind::PurchaseReceipt,
        };
    }

    public function isInbound(PurchaseInvoiceKind $kind): bool
    {
        return in_array($kind, [
            PurchaseInvoiceKind::PurchaseReceipt,
            PurchaseInvoiceKind::SalesReturn,
            PurchaseInvoiceKind::Amanat,
        ], true);
    }

    public function movementType(PurchaseInvoiceKind $kind): InventoryMovementType
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => InventoryMovementType::StockDocumentSalesReturn,
            PurchaseInvoiceKind::Amanat => InventoryMovementType::StockDocumentAmanatReturn,
            PurchaseInvoiceKind::PurchaseReturn => InventoryMovementType::StockDocumentPurchaseReturn,
            default => InventoryMovementType::PurchaseReceipt,
        };
    }

    public function reversalMovementType(PurchaseInvoiceKind $kind): InventoryMovementType
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => InventoryMovementType::StockDocumentSalesReturn,
            PurchaseInvoiceKind::Amanat => InventoryMovementType::StockDocumentAmanatOut,
            PurchaseInvoiceKind::PurchaseReturn => InventoryMovementType::PurchaseReceipt,
            default => InventoryMovementType::PurchaseReceiptReversal,
        };
    }

    public function stockTransactionCode(PurchaseInvoiceKind $kind): string
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => 'SALES_RETURN',
            PurchaseInvoiceKind::Amanat => 'AMANAT_RETURN',
            PurchaseInvoiceKind::PurchaseReturn => 'PURCHASE_RETURN',
            default => 'PURCHASE_ADD',
        };
    }

    public function stockDirection(PurchaseInvoiceKind $kind): string
    {
        return $this->isInbound($kind) ? 'in' : 'out';
    }

    public function affectsSupplierBalance(PurchaseInvoiceKind $kind): bool
    {
        return in_array($kind, [
            PurchaseInvoiceKind::PurchaseReceipt,
            PurchaseInvoiceKind::PurchaseReturn,
        ], true);
    }

    public function categoriesBalanceType(PurchaseInvoiceKind $kind): string
    {
        return match ($kind) {
            PurchaseInvoiceKind::SalesReturn => 'مرتجع مبيعات — مشتريات',
            PurchaseInvoiceKind::Amanat => 'امانات — مشتريات',
            PurchaseInvoiceKind::PurchaseReturn => 'مرتجع مشتريات',
            default => 'فواتير مشتريات',
        };
    }

    /** @return float Signed delta for suppliers.balance */
    public function supplierBalanceDelta(PurchaseInvoiceKind $kind, float $dueAmount, float $grandTotal): float
    {
        if (! $this->affectsSupplierBalance($kind)) {
            return 0.0;
        }

        if ($kind === PurchaseInvoiceKind::PurchaseReturn) {
            return -abs($dueAmount > 0.00001 ? $dueAmount : $grandTotal);
        }

        return (float) $dueAmount;
    }

    public function normalizeQuantity(PurchaseInvoiceKind $kind, float $qty): float
    {
        $abs = abs($qty);
        if ($abs <= 0.000001) {
            throw new \InvalidArgumentException('كمية البند يجب أن تكون أكبر من صفر.');
        }

        return $abs;
    }

    public function signedLineTotal(PurchaseInvoiceKind $kind, float $lineTotal): float
    {
        $abs = abs($lineTotal);

        return $this->isInbound($kind) ? $abs : -$abs;
    }
}
