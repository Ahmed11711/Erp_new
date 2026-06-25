<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case OpeningBalance = 'opening_balance';
    case PurchaseReceipt = 'purchase_receipt';
    case PurchaseReceiptReversal = 'purchase_receipt_reversal';
    case SaleIssue = 'sale_issue';
    case ProductionRawConsume = 'production_raw_consume';
    case ProductionToWip = 'production_to_wip';
    case ProductionOverhead = 'production_overhead';
    case ProductionToFinished = 'production_to_finished';
    case Transfer = 'transfer';
    case AdjustmentGain = 'adjustment_gain';
    case AdjustmentLoss = 'adjustment_loss';
    case RecipeExecution = 'recipe_execution';
    case ManualAdjustment = 'manual_adjustment';
    case LegacyStockMovement = 'legacy_stock_movement';

    /** Issued to a manufacturing production order (consumption from raw / WIP stock). */
    case ProductionOrderMaterialIssue = 'production_order_material_issue';

    /** Receipt of output from a manufacturing production order (WIP or finished). */
    case ProductionOrderOutput = 'production_order_output';

    /** Formal stock documents module — outbound voucher (صرف مخزون). */
    case StockDocumentOut = 'stock_document_out';

    /** Formal stock documents module — sales return (مرتجع مبيعات وارد للمخزون). */
    case StockDocumentSalesReturn = 'stock_document_sales_return';

    /** Formal stock documents module — purchase return (مرتجع مشتريات صادر من المخزون). */
    case StockDocumentPurchaseReturn = 'stock_document_purchase_return';

    /** Formal stock documents module — custody / amanat outbound. */
    case StockDocumentAmanatOut = 'stock_document_amanat_out';

    /** Formal stock documents module — custody / amanat return inbound. */
    case StockDocumentAmanatReturn = 'stock_document_amanat_return';

    /** Transfer leg — outbound warehouse line (paired with StockDocumentTransferIn). */
    case StockDocumentTransferOut = 'stock_document_transfer_out';

    /** Transfer leg — inbound warehouse line (paired with StockDocumentTransferOut). */
    case StockDocumentTransferIn = 'stock_document_transfer_in';

    /** Subcontracting — issue raw materials to external processor. */
    case SubcontractDispatchOut = 'subcontract_dispatch_out';

    /** Subcontracting — receive into materials-at-vendor pool. */
    case SubcontractDispatchIn = 'subcontract_dispatch_in';

    /** Subcontracting — good receipt out from vendor pool. */
    case SubcontractReceiptGoodOut = 'subcontract_receipt_good_out';

    /** Subcontracting — good receipt into destination warehouse. */
    case SubcontractReceiptGoodIn = 'subcontract_receipt_good_in';

    /** Subcontracting — rejected qty returned to raw warehouse. */
    case SubcontractReceiptRejectedOut = 'subcontract_receipt_rejected_out';

    case SubcontractReceiptRejectedIn = 'subcontract_receipt_rejected_in';

    /** Subcontracting — damaged / scrap write-off from vendor pool. */
    case SubcontractReceiptDamagedOut = 'subcontract_receipt_damaged_out';
}
