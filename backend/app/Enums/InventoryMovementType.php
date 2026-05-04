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
}
