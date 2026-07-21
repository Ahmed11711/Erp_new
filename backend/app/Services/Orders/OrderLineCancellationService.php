<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\OrderProduct;
use App\Models\ShippingCompany;
use App\Models\shippingCompanyDetails;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * إلغاء سطر (أو جزء منه) داخل نفس الطلب مع إعادة حساب الإجماليات والمحاسبة.
 */
final class OrderLineCancellationService
{
    /** @var list<string> */
    private const ALLOWED_STATUSES = ['طلب جديد', 'طلب مؤكد', 'شحن جزئي', 'تسليم جزئي'];

    /** @var list<string> */
    private const OPEN_SHIPPING_STATUSES = ['تم شحن', 'تم التسليم'];

    public function __construct(
        private OrderEditAccountingService $orderEditAccounting,
        private OrderCancellationAccountingService $orderCancellationAccounting,
    ) {
    }

    /**
     * @param  array<int, array{order_product_id: int, quantity: float|int, reason: string}>  $lines
     * @return array{order: Order, full_cancelled: bool, cancelled_amount: float, tracking_lines: list<string>}
     */
    public function cancelLines(Order $order, array $lines, int $userId, ?string $note = null): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('يجب تحديد سطر واحد على الأقل للإلغاء.');
        }

        if (! in_array((string) $order->order_status, self::ALLOWED_STATUSES, true)) {
            throw new RuntimeException('حالة الطلب الحالية «'.$order->order_status.'» لا تسمح بإلغاء أصناف.');
        }

        if ($order->order_status === 'ملغي') {
            throw new RuntimeException('الطلب ملغي بالفعل.');
        }

        return DB::transaction(function () use ($order, $lines, $userId, $note) {
            $locked = Order::query()
                ->lockForUpdate()
                ->with(['order_products.category', 'order_details'])
                ->findOrFail($order->id);

            $accountingBefore = $this->orderEditAccounting->snapshotBeforeEdit($locked);
            $trackingLines = [];
            $cancelledAmount = 0.0;

            foreach ($lines as $line) {
                $productId = (int) ($line['order_product_id'] ?? 0);
                $cancelQty = round((float) ($line['quantity'] ?? 0), 3);
                $reason = trim((string) ($line['reason'] ?? ''));

                if ($productId < 1 || $cancelQty <= 0.0001) {
                    throw new InvalidArgumentException('بيانات السطر غير صالحة.');
                }

                if ($reason === '') {
                    throw new InvalidArgumentException('سبب الإلغاء مطلوب لكل سطر.');
                }

                /** @var OrderProduct|null $orderProduct */
                $orderProduct = $locked->order_products->firstWhere('id', $productId);
                if (! $orderProduct) {
                    throw new RuntimeException('سطر الطلب #'.$productId.' غير موجود.');
                }

                $remaining = $this->remainingQuantity($orderProduct);
                if ($cancelQty > $remaining + 0.0001) {
                    $name = (string) ($orderProduct->category?->category_name ?? 'صنف #'.$orderProduct->category_id);
                    throw new RuntimeException(
                        'الكمية المراد إلغاؤها ('.$cancelQty.') أكبر من المتبقي ('.$remaining.') للصنف «'.$name.'».'
                    );
                }

                $lineAmount = round($cancelQty * (float) $orderProduct->price, 2);
                $cancelledAmount = round($cancelledAmount + $lineAmount, 2);

                $newCancelledQty = round((float) ($orderProduct->cancelled_quantity ?? 0) + $cancelQty, 3);
                $existingReason = trim((string) ($orderProduct->cancellation_reason ?? ''));
                $mergedReason = $existingReason === ''
                    ? $reason
                    : $existingReason."\n— ".$reason;

                $effectiveQty = max(0.0, round((float) $orderProduct->quantity - $newCancelledQty, 3));

                $orderProduct->cancelled_quantity = $newCancelledQty;
                $orderProduct->cancellation_reason = $mergedReason;
                $orderProduct->cancelled_at = now();
                $orderProduct->cancelled_by = $userId;
                $orderProduct->total_price = round($effectiveQty * (float) $orderProduct->price, 2);
                $orderProduct->save();

                $name = (string) ($orderProduct->category?->category_name ?? 'صنف #'.$orderProduct->category_id);
                $trackingLines[] = sprintf('إلغاء %s × %s — %s', $name, $this->formatQty($cancelQty), $reason);
            }

            $locked->refresh()->load('order_products');

            $this->recalculateOrderTotals($locked, $cancelledAmount);
            $this->adjustOpenShippingRows($locked);

            $fullCancelled = $this->shouldFullCancelOrder($locked->order_products);
            if ($fullCancelled) {
                $this->orderCancellationAccounting->handleCancellation($locked->fresh(['order_details']), $userId);
                $locked->order_status = 'ملغي';
                $locked->save();

                $orderDetails = OrderDetails::query()->where('order_id', $locked->id)->first();
                if ($orderDetails) {
                    $orderDetails->canceled_date = date('Y-m-d');
                    $orderDetails->status_date = date('Y-m-d');
                    $orderDetails->save();
                }

                $this->insertTracking($locked->id, 'طلب ملغي — إلغاء كل الأصناف', $userId, now());
            } else {
                $locked->save();
                $action = 'إلغاء أصناف من الطلب — '.implode(' | ', $trackingLines);
                $this->insertTracking($locked->id, $action, $userId, now());
            }

            $noteBody = implode("\n", $trackingLines);
            if ($note !== null && trim($note) !== '') {
                $noteBody .= "\n\nملاحظة: ".trim($note);
            }
            $this->insertNote($locked->id, $userId, $noteBody, 'إلغاء أصناف', now());

            $fresh = $locked->fresh(['order_products.category', 'order_details']);

            DB::afterCommit(function () use ($fresh, $accountingBefore, $userId, $fullCancelled) {
                if ($fullCancelled) {
                    return;
                }

                try {
                    $this->orderEditAccounting->reconcileAfterEdit(
                        (int) $fresh->id,
                        $accountingBefore,
                        $userId,
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            return [
                'order' => $fresh,
                'full_cancelled' => $fullCancelled,
                'cancelled_amount' => $cancelledAmount,
                'tracking_lines' => $trackingLines,
            ];
        });
    }

    public function remainingQuantity(OrderProduct $orderProduct): float
    {
        return max(
            0.0,
            round(
                (float) $orderProduct->quantity
                - (float) ($orderProduct->shipped_quantity ?? 0)
                - (float) ($orderProduct->cancelled_quantity ?? 0),
                3
            )
        );
    }

    private function recalculateOrderTotals(Order $order, float $cancelledAmount): void
    {
        if ($cancelledAmount <= 0.009) {
            return;
        }

        $discount = (float) ($order->discount ?? 0);
        $oldPrepaid = (float) ($order->prepaid_amount ?? 0);

        $newTotalInvoice = round(max(0.0, (float) ($order->total_invoice ?? 0) - $cancelledAmount), 2);
        $maxPrepaid = round(max(0.0, $newTotalInvoice - $discount), 2);
        $newPrepaid = round(min($oldPrepaid, $maxPrepaid), 2);
        $newNetTotal = round(max(0.0, $newTotalInvoice - $discount - $newPrepaid), 2);

        $order->total_invoice = $newTotalInvoice;
        $order->prepaid_amount = $newPrepaid;
        $order->net_total = $newNetTotal;
    }

    private function adjustOpenShippingRows(Order $order): void
    {
        $shippingRows = shippingCompanyDetails::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::OPEN_SHIPPING_STATUSES)
            ->where('is_done', 0)
            ->get();

        if ($shippingRows->isEmpty()) {
            return;
        }

        $sumOld = (float) $shippingRows->sum('amount');
        $newTotal = (float) $order->net_total;
        $allocated = 0.0;
        $count = $shippingRows->count();
        $i = 0;

        foreach ($shippingRows as $row) {
            ++$i;
            $old = (float) $row->amount;
            $share = $count === 1
                ? $newTotal
                : ($sumOld > 0.0001
                    ? round($newTotal * ($old / $sumOld), 3)
                    : round($newTotal / $count, 3));
            if ($i === $count) {
                $share = round($newTotal - $allocated, 3);
            }
            $allocated = round($allocated + $share, 3);

            ShippingCompany::query()->find($row->shipping_company_id)?->increment('balance', -$old + $share);
            $row->amount = $share;
            $row->save();
        }
    }

    /**
     * @param  Collection<int, OrderProduct>  $products
     */
    private function shouldFullCancelOrder(Collection $products): bool
    {
        if ($products->isEmpty()) {
            return false;
        }

        foreach ($products as $product) {
            if ((float) ($product->shipped_quantity ?? 0) > 0.009) {
                return false;
            }

            if ($this->remainingQuantity($product) > 0.009) {
                return false;
            }
        }

        return true;
    }

    private function insertTracking(int $orderId, string $action, int $userId, $createdAt): void
    {
        DB::table('trackings')->insert([
            'order_id' => $orderId,
            'date' => Carbon::parse($createdAt)->toDateString(),
            'action' => $action,
            'user_id' => $userId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertNote(int $orderId, int $userId, string $note, string $addedFrom, $createdAt): void
    {
        DB::table('notes')->insert([
            'order_id' => $orderId,
            'user_id' => $userId,
            'note' => $note,
            'added_from' => $addedFrom,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }
}
