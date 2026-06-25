<?php

namespace App\Services\Orders;

use App\Enums\InventoryMovementType;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\TreeAccount;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Inventory\InventoryMovementLedgerService;
use Illuminate\Support\Facades\DB;

final class OrderRollbackInventoryService
{
    private const ORDER_TYPES_LEGACY_SHIP = ['جديد', 'طلب استبدال'];

    public function __construct(
        private InventoryMovementLedgerService $ledger,
        private InventoryGlPostingService $inventoryGl,
    ) {
    }

    /**
     * @return list<array{type: string, category_id: int, quantity: float, reference: string}>
     */
    public function reverseShipInventory(Order $order, string $actor): array
    {
        $reversed = [];

        if (in_array($order->order_type, self::ORDER_TYPES_LEGACY_SHIP, true)) {
            $reversed = array_merge($reversed, $this->reverseLegacyShipInventory($order, $actor));
        } else {
            $reversed = array_merge($reversed, $this->reverseLedgerShipInventory($order, $actor));
        }

        return $reversed;
    }

    /**
     * @return list<array{type: string, category_id: int, quantity: float, reference: string}>
     */
    public function reverseCollectInventory(Order $order, string $actor): array
    {
        $reversed = [];
        $movements = InventoryMovement::query()
            ->where('reference_type', 'order_collect')
            ->where('reference_id', $order->id)
            ->where('direction', 'in')
            ->orderBy('id')
            ->get();

        foreach ($movements as $mov) {
            $cat = Category::query()->lockForUpdate()->find((int) $mov->category_id);
            if (! $cat) {
                continue;
            }

            $qty = (float) $mov->quantity;
            $uc = (float) $mov->unit_cost;
            $tc = (float) $mov->total_cost;

            $this->ledger->recordOutbound(
                $cat,
                InventoryMovementType::SaleIssue,
                $qty,
                $uc,
                $tc,
                true,
                'order_collect_reversal',
                $order->id,
                'عكس تحصيل — إرجاع مخزون — طلب ' . $order->id,
                null,
                $actor,
            );

            $reversed[] = [
                'type' => 'order_collect_reversal',
                'category_id' => (int) $mov->category_id,
                'quantity' => $qty,
                'reference' => 'order_collect_reversal:' . $order->id,
            ];
        }

        return $reversed;
    }

    /**
     * @return list<array{type: string, category_id: int, quantity: float, reference: string}>
     */
    private function reverseLegacyShipInventory(Order $order, string $actor): array
    {
        $reversed = [];
        $products = OrderProduct::query()->where('order_id', $order->id)->get();
        $totalCogsReturn = 0.0;
        $restoreByInv = [];

        foreach ($products as $op) {
            $shippedQty = (float) $op->shipped_quantity;
            if ($op->quantity <= 0 || $shippedQty <= 0) {
                continue;
            }

            $categoryId = (int) $op->category_id;
            $avgCost = CategoryInventoryCostService::resolveReferenceUnitCost($categoryId);
            $lineRet = $avgCost * $shippedQty;
            $totalCogsReturn += $lineRet;

            $invAcc = TreeAccount::resolveInventoryAccountForCategoryId($categoryId);
            if ($invAcc && $lineRet > 0.000001) {
                $restoreByInv[$invAcc->id] = ($restoreByInv[$invAcc->id] ?? 0) + $lineRet;
            }

            DB::statement('CALL category_procedure(?, ?, ?, ?, ?, ?, ?)', [
                $categoryId,
                $order->id,
                'إعادة فتح طلب',
                $shippedQty,
                (float) $op->price,
                $actor,
                now(),
            ]);

            Category::find($categoryId)?->increment(
                'sell_total_price',
                -((float) $op->price * $shippedQty)
            );

            $op->shipped_quantity = 0;
            $op->save();

            $reversed[] = [
                'type' => 'category_procedure_reversal',
                'category_id' => $categoryId,
                'quantity' => $shippedQty,
                'reference' => 'category_procedure:' . $order->id,
            ];
        }

        if ($totalCogsReturn > 0.00001) {
            if ($restoreByInv === []) {
                $fb = TreeAccount::resolveInventoryAccount();
                if ($fb) {
                    $restoreByInv[$fb->id] = $totalCogsReturn;
                }
            }

            $this->inventoryGl->postSalesReturnInventoryRestoreByWarehouse(
                $restoreByInv,
                'إعادة فتح طلب — إرجاع تكلفة للمخزون — طلب ' . $order->id,
                auth()->id(),
            );

            $reversed[] = [
                'type' => 'cogs_reversal',
                'category_id' => 0,
                'quantity' => $totalCogsReturn,
                'reference' => 'cogs_reversal:' . $order->id,
            ];
        }

        return $reversed;
    }

    /**
     * @return list<array{type: string, category_id: int, quantity: float, reference: string}>
     */
    private function reverseLedgerShipInventory(Order $order, string $actor): array
    {
        $reversed = [];
        $movements = InventoryMovement::query()
            ->where('reference_type', 'order_ship')
            ->where('reference_id', $order->id)
            ->where('direction', 'out')
            ->orderBy('id')
            ->get();

        foreach ($movements as $mov) {
            $cat = Category::query()->lockForUpdate()->find((int) $mov->category_id);
            if (! $cat) {
                continue;
            }

            $qty = (float) $mov->quantity;
            $uc = (float) $mov->unit_cost;
            $tc = (float) $mov->total_cost;

            $this->ledger->recordInbound(
                $cat,
                InventoryMovementType::SaleIssue,
                $qty,
                $uc,
                $tc,
                true,
                'order_ship_reversal',
                $order->id,
                'عكس شحن — إعادة فتح طلب ' . $order->id,
                null,
                $actor,
            );

            $reversed[] = [
                'type' => 'order_ship_reversal',
                'category_id' => (int) $mov->category_id,
                'quantity' => $qty,
                'reference' => 'order_ship_reversal:' . $order->id,
            ];
        }

        OrderProduct::query()
            ->where('order_id', $order->id)
            ->update(['shipped_quantity' => 0]);

        return $reversed;
    }
}
