<?php

namespace App\Services\Orders;

use App\Models\Bank;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Safe;
use App\Models\ServiceAccount;
use App\Models\TreeAccount;
use App\Services\Accounting\InventoryGlPostingService;
use App\Services\Accounting\SalesOrderAccountingService;
use App\Services\CategoryInventoryCostService;
use App\Services\Shipping\OrderFinancialStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * تسوية محاسبة الطلب بعد التعديل: قيود الفاتورة، الذمم، COGS للمشحون، وأرصدة البنوك التشغيلية.
 */
class OrderEditAccountingService
{
    /** يمنع قيود PARTCOLLECT/PREPAID-REV المكررة أثناء إعادة بناء ORD-PREPAID في نفس التعديل. */
    public static bool $suppressPrepaidDeltaJournal = false;

    public function __construct(
        private SalesOrderAccountingService $salesOrderAccounting,
        private OrderFinancialStateService $financialState,
        private InventoryGlPostingService $inventoryGl,
    ) {
    }

    /**
     * @param  array{
     *   prepaid_amount: float,
     *   bank_id: mixed,
     *   order_status: string,
     *   order_type: string,
     *   products: array<int, array{category_id: int, quantity: float, shipped_quantity: float}>
     * }  $before
     */
    public function reconcileAfterEdit(int $orderId, array $before, int $userId, ?string $paymentType = null, mixed $paymentSourceId = null): void
    {
        $fresh = Order::query()
            ->with([
                'order_products',
                'order_details.shipping_company',
                'order_details.collection_company',
            ])
            ->find($orderId);

        if (! $fresh) {
            return;
        }

        $prepaidChanged = abs((float) $fresh->prepaid_amount - (float) ($before['prepaid_amount'] ?? 0)) > 0.009;
        $financialChanged = $this->financialFieldsChanged($fresh, $before);

        if ($financialChanged || $prepaidChanged) {
            try {
                $this->salesOrderAccounting->refreshOrderRecognition($fresh, $prepaidChanged);
            } catch (\Throwable $e) {
                Log::error('OrderEditAccountingService: refreshOrderRecognition failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->financialState->syncFromOrder($fresh);
        } catch (\Throwable $e) {
            Log::error('OrderEditAccountingService: syncFromOrder failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($prepaidChanged) {
            $this->applyPrepaidOperationalBalanceDiff(
                $fresh,
                (float) ($before['prepaid_amount'] ?? 0),
                (float) $fresh->prepaid_amount,
                $userId,
                $paymentType,
                $paymentSourceId
            );
        }

        if (($before['order_status'] ?? '') === 'تم شحن') {
            $this->reconcileShippedCogs($fresh, $before['products'] ?? [], $userId);
        }
    }

    /**
     * @param  array{
     *   prepaid_amount: float,
     *   bank_id: mixed,
     *   order_status: string,
     *   order_type: string,
     *   products: array<int, array{category_id: int, quantity: float, shipped_quantity: float}>
     * }  $before
     */
    public function snapshotBeforeEdit(Order $order): array
    {
        $order->loadMissing('order_products');

        return [
            'prepaid_amount' => (float) ($order->prepaid_amount ?? 0),
            'bank_id' => $order->bank_id,
            'order_status' => (string) $order->order_status,
            'order_type' => (string) $order->order_type,
            'total_invoice' => (float) ($order->total_invoice ?? 0),
            'discount' => (float) ($order->discount ?? 0),
            'shipping_revenue' => (float) ($order->shipping_revenue ?? $order->shipping_cost ?? 0),
            'shipping_cost' => (float) ($order->shipping_cost ?? 0),
            'net_total' => (float) ($order->net_total ?? 0),
            'products' => $order->order_products->map(static fn (OrderProduct $op) => [
                'category_id' => (int) $op->category_id,
                'quantity' => (float) $op->quantity,
                'shipped_quantity' => (float) ($op->shipped_quantity ?? 0),
                'price' => (float) $op->price,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array{category_id: int, quantity: float, shipped_quantity: float}>  $beforeProducts
     */
    private function reconcileShippedCogs(Order $order, array $beforeProducts, int $userId): void
    {
        if (! in_array($order->order_type, ['جديد', 'طلب استبدال'], true)) {
            return;
        }

        $oldByCat = collect($beforeProducts)->keyBy('category_id');
        $newByCat = $order->order_products->keyBy('category_id');

        $increaseByInv = [];
        $decreaseByInv = [];

        foreach ($newByCat as $categoryId => $op) {
            $qty = (float) ($op->shipped_quantity ?: $op->quantity);
            $oldQty = (float) ($oldByCat->get($categoryId)['shipped_quantity'] ?? $oldByCat->get($categoryId)['quantity'] ?? 0);
            $deltaQty = round($qty - $oldQty, 4);
            if (abs($deltaQty) < 0.0001) {
                continue;
            }

            $unitCost = CategoryInventoryCostService::averageCostForCategoryIssue((int) $categoryId);
            $lineCogs = round(abs($deltaQty) * $unitCost, 2);
            if ($lineCogs <= 0.009) {
                continue;
            }

            $invAcc = TreeAccount::resolveInventoryAccountForCategoryId((int) $categoryId);
            if (! $invAcc) {
                continue;
            }

            if ($deltaQty > 0) {
                $increaseByInv[$invAcc->id] = ($increaseByInv[$invAcc->id] ?? 0) + $lineCogs;
            } else {
                $decreaseByInv[$invAcc->id] = ($decreaseByInv[$invAcc->id] ?? 0) + $lineCogs;
            }
        }

        foreach ($oldByCat as $categoryId => $row) {
            if ($newByCat->has($categoryId)) {
                continue;
            }
            $oldQty = (float) ($row['shipped_quantity'] ?: $row['quantity'] ?? 0);
            if ($oldQty <= 0.0001) {
                continue;
            }
            $unitCost = CategoryInventoryCostService::averageCostForCategoryIssue((int) $categoryId);
            $lineCogs = round($oldQty * $unitCost, 2);
            if ($lineCogs <= 0.009) {
                continue;
            }
            $invAcc = TreeAccount::resolveInventoryAccountForCategoryId((int) $categoryId);
            if ($invAcc) {
                $decreaseByInv[$invAcc->id] = ($decreaseByInv[$invAcc->id] ?? 0) + $lineCogs;
            }
        }

        $increaseTotal = array_sum($increaseByInv);
        if ($increaseTotal > 0.009) {
            try {
                $this->inventoryGl->postCogsShipment(
                    $increaseTotal,
                    $increaseByInv,
                    'تعديل طلب مشحون — زيادة تكلفة مبيعات — طلب رقم '.$order->id
                );
            } catch (\Throwable $e) {
                Log::warning('OrderEditAccountingService: COGS increase failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $decreaseTotal = array_sum($decreaseByInv);
        if ($decreaseTotal > 0.009) {
            try {
                $this->inventoryGl->postSalesReturnInventoryRestoreByWarehouse(
                    $decreaseByInv,
                    'تعديل طلب مشحون — تخفيض تكلفة مبيعات — طلب رقم '.$order->id,
                    $userId
                );
            } catch (\Throwable $e) {
                Log::warning('OrderEditAccountingService: COGS decrease failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function applyPrepaidOperationalBalanceDiff(
        Order $order,
        float $oldPrepaid,
        float $newPrepaid,
        int $userId,
        ?string $paymentType,
        mixed $paymentSourceId,
    ): void {
        $diff = round($newPrepaid - $oldPrepaid, 2);
        if (abs($diff) < 0.009) {
            return;
        }

        $details = 'فرق دفعة مقدمة من تعديل الطلب رقم '.$order->id;
        $ref = (string) $order->id;
        $type = 'الطلبات';
        $createdAt = now();

        $paymentType = $paymentType ?? 'bank';

        if ($paymentType === 'safe' && $paymentSourceId) {
            $safe = Safe::find($paymentSourceId);
            if ($safe) {
                $this->adjustSafeBalance($safe, $diff, $order->id, $userId, $details, $ref, $type, $createdAt);
            }

            return;
        }

        if ($paymentType === 'service_account' && $paymentSourceId) {
            $svc = ServiceAccount::find($paymentSourceId);
            if ($svc) {
                $svc->update(['balance' => (float) $svc->balance + $diff]);
            }

            return;
        }

        $bankId = $order->bank_id ?? $paymentSourceId;
        if ($bankId !== null && $bankId !== '' && $bankId !== 'null') {
            $bank = Bank::find($bankId);
            if ($bank) {
                $this->adjustBankBalance((int) $bank->id, $diff, $order->id, $userId, $details, $ref, $type, $createdAt);
            }
        }
    }

    private function adjustBankBalance(int $bankId, float $amount, int $orderId, int $userId, string $details, string $ref, string $type, $createdAt): void
    {
        $bank = DB::table('banks')->where('id', $bankId)->first();
        if (! $bank) {
            return;
        }

        $currentBalance = (float) $bank->balance;
        $newBalance = $currentBalance + $amount;

        DB::table('banks')->where('id', $bankId)->update(['balance' => $newBalance]);
        DB::table('bank_details')->insert([
            'bank_id' => $bankId,
            'details' => $details,
            'ref' => $ref,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $newBalance,
            'date' => date('Y-m-d'),
            'created_at' => $createdAt,
            'user_id' => $userId,
        ]);
    }

    private function adjustSafeBalance(Safe $safe, float $amount, int $orderId, int $userId, string $details, string $ref, string $type, $createdAt): void
    {
        app(\App\Services\Accounting\SafeOperationalLedgerService::class)->recordOperationalMovement(
            $safe,
            $amount,
            trim($details . ' — طلب #' . $orderId),
            $ref,
            $type,
            $userId,
            \Carbon\Carbon::parse($createdAt)->toDateString()
        );
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function financialFieldsChanged(Order $order, array $before): bool
    {
        $checks = [
            'total_invoice',
            'discount',
            'shipping_revenue',
            'shipping_cost',
            'net_total',
            'prepaid_amount',
        ];

        foreach ($checks as $field) {
            if (abs((float) ($order->{$field} ?? 0) - (float) ($before[$field] ?? 0)) > 0.009) {
                return true;
            }
        }

        $oldProducts = collect($before['products'] ?? []);
        $newProducts = $order->order_products;

        if ($oldProducts->count() !== $newProducts->count()) {
            return true;
        }

        $newByCat = $newProducts->keyBy('category_id');
        foreach ($oldProducts as $row) {
            $catId = (int) ($row['category_id'] ?? 0);
            $newRow = $newByCat->get($catId);
            if (! $newRow) {
                return true;
            }
            if (abs((float) $newRow->quantity - (float) ($row['quantity'] ?? 0)) > 0.009) {
                return true;
            }
            if (abs((float) $newRow->price - (float) ($row['price'] ?? $newRow->price)) > 0.009) {
                return true;
            }
        }

        return false;
    }
}
