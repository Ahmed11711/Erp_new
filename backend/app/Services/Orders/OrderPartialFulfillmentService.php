<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderShipment;
use App\Models\OrderShipmentLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تسليم/شحن جزئي احترافي:
 * - المتبقي = quantity − shipped − cancelled
 * - حالة «تسليم جزئي» عندما يُؤكَّد تسليم ما شُحن وبقي كمية مفتوحة
 * - سجل order_shipments لكل دفعة شحن (COGS مرتبط بالدفعة)
 *
 * محاسبة عروض الأسعار: المديونية/الإيراد كاملان عند التحويل (OFFER-*)؛
 * عند كل شحن تُرحَّل تكلفة البضاعة فقط (COGS) للكمية المشحونة — بدون تكرار ذمة.
 */
class OrderPartialFulfillmentService
{
    public const STATUS_PARTIAL_SHIP = 'شحن جزئي';

    public const STATUS_PARTIAL_DELIVER = 'تسليم جزئي';

    public const STATUS_SHIPPED = 'تم شحن';

    public const STATUS_DELIVERED = 'تم التسليم';

    /** حالات يُسمح منها بشحن كميات إضافية (شركة). */
    public const SHIPPABLE_COMPANY = [
        'طلب جديد',
        'طلب مؤكد',
        self::STATUS_PARTIAL_SHIP,
        self::STATUS_PARTIAL_DELIVER,
        'مؤجل',
    ];

    /** حالات يُسمح منها بشحن (أفراد). */
    public const SHIPPABLE_INDIVIDUAL = [
        'طلب مؤكد',
        self::STATUS_PARTIAL_SHIP,
        self::STATUS_PARTIAL_DELIVER,
        'مؤجل',
    ];

    /** حالات يُسمح منها بتأكيد التسليم. */
    public const DELIVERABLE = [
        self::STATUS_SHIPPED,
        self::STATUS_PARTIAL_SHIP,
        self::STATUS_PARTIAL_DELIVER,
    ];

    /**
     * أنواع الطلبات التي يُتابع فيها الشحن على مستوى الصنف (shipped_quantity).
     * غيرها (صيانة / مرتجع) تُشحن بالكامل بحالة الطلب دون تحديث كميات الأسطر،
     * فلا يصح اعتبار كمياتها متبقية عند التسليم.
     */
    public const PARTIAL_TRACKED_ORDER_TYPES = ['جديد', 'طلب استبدال'];

    public function remainingQuantity(OrderProduct $line): float
    {
        return max(
            0.0,
            round(
                (float) $line->quantity
                - (float) ($line->shipped_quantity ?? 0)
                - (float) ($line->cancelled_quantity ?? 0),
                3
            )
        );
    }

    public function tracksPartialFulfillment(Order $order): bool
    {
        return in_array(trim((string) $order->order_type), self::PARTIAL_TRACKED_ORDER_TYPES, true);
    }

    /** تسليم جزئي فعلي: نوع الطلب يتابع الكميات + بقي كمية غير مشحونة. */
    public function shouldRecordPartialDelivery(Order $order): bool
    {
        return $this->tracksPartialFulfillment($order) && $this->hasOpenRemaining($order);
    }

    public function hasOpenRemaining(Order $order): bool
    {
        $order->loadMissing('order_products');

        foreach ($order->order_products as $line) {
            if ($this->remainingQuantity($line) > 0.009) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *   ordered_qty: float,
     *   shipped_qty: float,
     *   cancelled_qty: float,
     *   remaining_qty: float,
     *   ordered_value: float,
     *   shipped_value: float,
     *   remaining_value: float,
     *   percent_shipped: float,
     *   is_fully_shipped: bool,
     *   open_lines: int
     * }
     */
    public function progress(Order $order): array
    {
        $order->loadMissing('order_products');

        $orderedQty = 0.0;
        $shippedQty = 0.0;
        $cancelledQty = 0.0;
        $orderedValue = 0.0;
        $shippedValue = 0.0;
        $openLines = 0;

        foreach ($order->order_products as $line) {
            $qty = (float) $line->quantity;
            $shipped = (float) ($line->shipped_quantity ?? 0);
            $cancelled = (float) ($line->cancelled_quantity ?? 0);
            $price = (float) ($line->price ?? 0);
            $remaining = $this->remainingQuantity($line);

            $orderedQty += $qty;
            $shippedQty += $shipped;
            $cancelledQty += $cancelled;
            $orderedValue += ($qty - $cancelled) * $price;
            $shippedValue += min($shipped, max(0, $qty - $cancelled)) * $price;

            if ($remaining > 0.009) {
                $openLines++;
            }
        }

        $effectiveOrdered = max(0.0, $orderedQty - $cancelledQty);
        $remainingQty = max(0.0, round($effectiveOrdered - $shippedQty, 3));
        $remainingValue = max(0.0, round($orderedValue - $shippedValue, 2));
        $percent = $effectiveOrdered > 0.009
            ? round(min(100, ($shippedQty / $effectiveOrdered) * 100), 1)
            : 100.0;

        return [
            'ordered_qty' => round($orderedQty, 3),
            'shipped_qty' => round($shippedQty, 3),
            'cancelled_qty' => round($cancelledQty, 3),
            'remaining_qty' => $remainingQty,
            'ordered_value' => round($orderedValue, 2),
            'shipped_value' => round($shippedValue, 2),
            'remaining_value' => $remainingValue,
            'percent_shipped' => $percent,
            'is_fully_shipped' => $remainingQty <= 0.009,
            'open_lines' => $openLines,
        ];
    }

    public function statusAfterShip(bool $fullyShipped): string
    {
        return $fullyShipped ? self::STATUS_SHIPPED : self::STATUS_PARTIAL_SHIP;
    }

    /**
     * يعلّم كل أسطر الطلب مشحونة بالكامل (الكمية − الملغي).
     * تُستخدم لأنواع الطلبات التي تُشحن بحالة الطلب دفعة واحدة (صيانة / مرتجع)
     * حتى لا يظهر متبقٍ وهمي عند التسليم والتقارير.
     *
     * @return int عدد الأسطر التي تغيّرت
     */
    public function markAllLinesShipped(Order $order): int
    {
        $order->loadMissing('order_products');
        $updated = 0;

        foreach ($order->order_products as $line) {
            // أسطر المرتجع بكميات سالبة تُترك كما هي.
            if ((float) $line->quantity <= 0) {
                continue;
            }

            $target = max(
                0.0,
                round((float) $line->quantity - (float) ($line->cancelled_quantity ?? 0), 3)
            );

            if (abs((float) ($line->shipped_quantity ?? 0) - $target) < 0.0001) {
                continue;
            }

            $line->shipped_quantity = $target;
            $line->save();
            $updated++;
        }

        return $updated;
    }

    public function statusAfterDeliver(Order $order): string
    {
        return $this->shouldRecordPartialDelivery($order)
            ? self::STATUS_PARTIAL_DELIVER
            : self::STATUS_DELIVERED;
    }

    /**
     * يسجّل دفعة شحن للتدقيق وربط تكلفة البضاعة بالدفعة.
     *
     * @param  list<array{
     *   order_product_id: int,
     *   category_id: int|null,
     *   quantity: float,
     *   unit_price: float,
     *   unit_cost: float,
     *   line_cogs: float
     * }>  $lines
     */
    public function recordShipment(
        Order $order,
        array $lines,
        bool $isFinal,
        string $statusAfter,
        ?int $shippingCompanyId,
        ?string $paymentWay,
        ?string $shippedAt,
        ?int $userId,
        ?string $notes = null,
    ): OrderShipment {
        $seq = (int) OrderShipment::query()
            ->where('order_id', $order->id)
            ->max('shipment_seq') + 1;

        $linesTotal = 0.0;
        $cogsTotal = 0.0;
        foreach ($lines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);
            $linesTotal += $qty * $price;
            $cogsTotal += (float) ($line['line_cogs'] ?? 0);
        }

        return DB::transaction(function () use (
            $order,
            $lines,
            $seq,
            $isFinal,
            $statusAfter,
            $shippingCompanyId,
            $paymentWay,
            $shippedAt,
            $userId,
            $notes,
            $linesTotal,
            $cogsTotal,
        ) {
            $shipment = OrderShipment::create([
                'order_id' => $order->id,
                'shipment_seq' => $seq,
                'shipped_at' => $shippedAt ?: now()->toDateString(),
                'shipping_company_id' => $shippingCompanyId ?: null,
                'payment_way' => $paymentWay,
                'lines_total' => round($linesTotal, 2),
                'cogs_total' => round($cogsTotal, 4),
                'is_final' => $isFinal,
                'status_after' => $statusAfter,
                'created_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $qty = (float) ($line['quantity'] ?? 0);
                $price = (float) ($line['unit_price'] ?? 0);
                OrderShipmentLine::create([
                    'order_shipment_id' => $shipment->id,
                    'order_product_id' => (int) $line['order_product_id'],
                    'category_id' => isset($line['category_id']) ? (int) $line['category_id'] : null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => round($qty * $price, 2),
                    'unit_cost' => round((float) ($line['unit_cost'] ?? 0), 4),
                    'line_cogs' => round((float) ($line['line_cogs'] ?? 0), 4),
                ]);
            }

            return $shipment;
        });
    }

    /**
     * عند تأكيد التسليم (كامل أو جزئي) تُعلَّم الدفعات غير المسلَّمة كمسلَّمة
     * حتى لا تبقى ضمن بضاعة المندوب.
     */
    public function markUndeliveredShipmentsDelivered(Order $order, int $userId): int
    {
        return (int) OrderShipment::query()
            ->where('order_id', $order->id)
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => now(),
                'delivered_by' => $userId,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  Collection<int, Order>|iterable<Order>  $orders
     */
    public function attachProgressToOrders(iterable $orders): void
    {
        foreach ($orders as $order) {
            if ($order instanceof Order) {
                $order->setAttribute('fulfillment_progress', $this->progress($order));
            }
        }
    }
}
