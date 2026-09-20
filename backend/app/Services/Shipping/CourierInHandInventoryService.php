<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderShipment;
use App\Models\ShippingCompany;
use App\Services\Orders\OrderPartialFulfillmentService;
use Illuminate\Support\Facades\DB;

/**
 * بضاعة المندوب/شركة الشحن: مشحونة ولم يُؤكَّد تسليمها بعد.
 */
class CourierInHandInventoryService
{
    public function __construct(private OrderPartialFulfillmentService $fulfillment)
    {
    }

    /**
     * @return array{
     *   company: array{id: int, name: mixed, type: mixed},
     *   totals: array{
     *     orders_count: int,
     *     deliverable_count: int,
     *     sku_count: int,
     *     in_hand_qty: float,
     *     in_hand_value: float
     *   },
     *   sku_summary: list<array<string, mixed>>,
     *   orders: list<array<string, mixed>>
     * }
     */
    public function forCompany(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $company = ShippingCompany::query()->findOrFail($companyId);

        $fromShipments = $this->ordersFromUndeliveredShipments($companyId, $dateFrom, $dateTo);
        $fromLegacy = $this->ordersFromLegacyShippedDetails(
            $companyId,
            $dateFrom,
            $dateTo,
            array_keys($fromShipments)
        );

        $merged = $fromShipments + $fromLegacy;

        $orders = [];
        $skuMap = [];
        $totalQty = 0.0;
        $totalValue = 0.0;
        $deliverableCount = 0;

        foreach ($merged as $row) {
            if ($row['in_hand_qty'] <= 0.009) {
                continue;
            }

            $orders[] = $row;
            if (! empty($row['can_deliver'])) {
                $deliverableCount++;
            }
            $totalQty += (float) $row['in_hand_qty'];
            $totalValue += (float) $row['in_hand_value'];

            foreach ($row['lines'] as $line) {
                $key = (string) (($line['category_id'] ?? 0) ?: ('x-'.$line['product_name']));
                if (! isset($skuMap[$key])) {
                    $skuMap[$key] = [
                        'category_id' => $line['category_id'] ?? null,
                        'item_code' => $line['item_code'] ?? null,
                        'product_name' => $line['product_name'],
                        'qty' => 0.0,
                        'value' => 0.0,
                        'order_ids' => [],
                    ];
                }
                $skuMap[$key]['qty'] += (float) $line['qty'];
                $skuMap[$key]['value'] += (float) $line['value'];
                $skuMap[$key]['order_ids'][(int) $row['order_id']] = true;
            }
        }

        foreach ($skuMap as &$sku) {
            $sku['qty'] = round((float) $sku['qty'], 3);
            $sku['value'] = round((float) $sku['value'], 2);
            $sku['orders_count'] = count($sku['order_ids']);
            unset($sku['order_ids']);
        }
        unset($sku);

        usort($orders, static fn ($a, $b) => $b['order_id'] <=> $a['order_id']);
        $skuSummary = array_values($skuMap);
        usort($skuSummary, static fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return [
            'company' => [
                'id' => (int) $company->id,
                'name' => $company->name,
                'type' => $company->type,
            ],
            'totals' => [
                'orders_count' => count($orders),
                'deliverable_count' => $deliverableCount,
                'sku_count' => count($skuSummary),
                'in_hand_qty' => round($totalQty, 3),
                'in_hand_value' => round($totalValue, 2),
            ],
            'sku_summary' => $skuSummary,
            'orders' => array_values($orders),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ordersFromUndeliveredShipments(int $companyId, ?string $dateFrom, ?string $dateTo): array
    {
        $shipments = OrderShipment::query()
            ->where('shipping_company_id', $companyId)
            ->whereNull('delivered_at')
            ->when($dateFrom, fn ($q) => $q->whereDate('shipped_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('shipped_at', '<=', $dateTo))
            ->with([
                'order:id,customer_name,customer_phone_1,order_status,net_total',
                'order.order_products',
                'order.order_shipment_number:id,order_id,shipment_number',
                'lines.category:id,category_name,item_code',
                'lines.orderProduct:id,category_id,quantity,shipped_quantity,cancelled_quantity,price',
            ])
            ->orderByDesc('id')
            ->get();

        $byOrder = [];
        foreach ($shipments as $shipment) {
            $order = $shipment->order;
            if (! $order) {
                continue;
            }

            $oid = (int) $order->id;
            if (! isset($byOrder[$oid])) {
                $byOrder[$oid] = $this->baseOrderRow($order);
            }

            $shippedAt = optional($shipment->shipped_at)->format('Y-m-d');
            if ($shippedAt && ($byOrder[$oid]['shipped_at'] === null || $shippedAt < $byOrder[$oid]['shipped_at'])) {
                $byOrder[$oid]['shipped_at'] = $shippedAt;
            }

            foreach ($shipment->lines as $line) {
                $qty = (float) $line->quantity;
                if ($qty <= 0.0001) {
                    continue;
                }
                $pid = (int) $line->order_product_id;
                $price = (float) $line->unit_price;
                $cat = $line->category;
                $op = $line->orderProduct;

                if (! isset($byOrder[$oid]['_lineMap'][$pid])) {
                    $byOrder[$oid]['_lineMap'][$pid] = [
                        'order_product_id' => $pid,
                        'category_id' => $line->category_id ? (int) $line->category_id : null,
                        'item_code' => $cat?->item_code,
                        'product_name' => $cat?->category_name ?: ('صنف #'.($line->category_id ?: $pid)),
                        'qty' => 0.0,
                        'value' => 0.0,
                        'unit_price' => $price,
                        'ordered_qty' => $op ? (float) $op->quantity : null,
                        'remaining_unshipped_qty' => $op ? $this->fulfillment->remainingQuantity($op) : null,
                    ];
                }

                $byOrder[$oid]['_lineMap'][$pid]['qty'] += $qty;
                $byOrder[$oid]['_lineMap'][$pid]['value'] += $qty * $price;
                $byOrder[$oid]['in_hand_qty'] += $qty;
                $byOrder[$oid]['in_hand_value'] += $qty * $price;
            }
        }

        return $this->finalizeOrderRows($byOrder);
    }

    /**
     * طلبات قديمة بلا سجل order_shipments: المشحون وغير المسلَّم على سطر شركة الشحن.
     *
     * @param  list<int>  $alreadyOrderIds
     * @return array<int, array<string, mixed>>
     */
    private function ordersFromLegacyShippedDetails(
        int $companyId,
        ?string $dateFrom,
        ?string $dateTo,
        array $alreadyOrderIds
    ): array {
        $rows = DB::table('shipping_company_details as scd')
            ->join('orders as o', 'o.id', '=', 'scd.order_id')
            ->where('scd.shipping_company_id', $companyId)
            ->where('scd.status', 'تم شحن')
            ->where('scd.is_done', 0)
            ->whereIn('o.order_status', [
                OrderPartialFulfillmentService::STATUS_SHIPPED,
                OrderPartialFulfillmentService::STATUS_PARTIAL_SHIP,
            ])
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('order_shipments as os')
                    ->whereColumn('os.order_id', 'o.id');
            })
            ->when($alreadyOrderIds !== [], fn ($q) => $q->whereNotIn('o.id', $alreadyOrderIds))
            ->when($dateFrom, fn ($q) => $q->whereDate('scd.shipping_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('scd.shipping_date', '<=', $dateTo))
            ->select('o.id')
            ->distinct()
            ->pluck('id');

        if ($rows->isEmpty()) {
            return [];
        }

        $orders = Order::query()
            ->whereIn('id', $rows->all())
            ->with([
                'order_products.category:id,category_name,item_code',
                'order_shipment_number:id,order_id,shipment_number',
                'order_details:id,order_id,shipping_date',
            ])
            ->get();

        $byOrder = [];
        foreach ($orders as $order) {
            $oid = (int) $order->id;
            $row = $this->baseOrderRow($order);
            $row['shipped_at'] = $order->order_details?->shipping_date
                ? substr((string) $order->order_details->shipping_date, 0, 10)
                : null;

            foreach ($order->order_products as $op) {
                /** @var OrderProduct $op */
                $qty = (float) ($op->shipped_quantity ?? 0);
                if ($qty <= 0.009) {
                    continue;
                }
                $price = (float) ($op->price ?? 0);
                $cat = $op->category;
                $pid = (int) $op->id;
                $row['_lineMap'][$pid] = [
                    'order_product_id' => $pid,
                    'category_id' => $op->category_id ? (int) $op->category_id : null,
                    'item_code' => $cat?->item_code,
                    'product_name' => $cat?->category_name ?: ('صنف #'.($op->category_id ?: $pid)),
                    'qty' => $qty,
                    'value' => $qty * $price,
                    'unit_price' => $price,
                    'ordered_qty' => (float) $op->quantity,
                    'remaining_unshipped_qty' => $this->fulfillment->remainingQuantity($op),
                ];
                $row['in_hand_qty'] += $qty;
                $row['in_hand_value'] += $qty * $price;
            }

            $byOrder[$oid] = $row;
        }

        return $this->finalizeOrderRows($byOrder);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseOrderRow(Order $order): array
    {
        $progress = $this->fulfillment->progress($order);
        $hasRemaining = $progress['remaining_qty'] > 0.009;

        return [
            'order_id' => (int) $order->id,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone_1,
            'order_status' => $order->order_status,
            'net_total' => round((float) $order->net_total, 2),
            'shipment_numbers' => $order->order_shipment_number
                ? $order->order_shipment_number->pluck('shipment_number')->filter()->values()->all()
                : [],
            'can_deliver' => in_array($order->order_status, OrderPartialFulfillmentService::DELIVERABLE, true),
            'will_mark_status' => $hasRemaining
                ? OrderPartialFulfillmentService::STATUS_PARTIAL_DELIVER
                : OrderPartialFulfillmentService::STATUS_DELIVERED,
            'remaining_unshipped_qty' => $progress['remaining_qty'],
            'in_hand_qty' => 0.0,
            'in_hand_value' => 0.0,
            'shipped_at' => null,
            'lines' => [],
            '_lineMap' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $byOrder
     * @return array<int, array<string, mixed>>
     */
    private function finalizeOrderRows(array $byOrder): array
    {
        foreach ($byOrder as &$row) {
            $map = $row['_lineMap'] ?? [];
            $row['lines'] = array_values(array_map(static function ($line) {
                $line['qty'] = round((float) $line['qty'], 3);
                $line['value'] = round((float) $line['value'], 2);

                return $line;
            }, $map));
            unset($row['_lineMap']);
            $row['in_hand_qty'] = round((float) $row['in_hand_qty'], 3);
            $row['in_hand_value'] = round((float) $row['in_hand_value'], 2);
        }
        unset($row);

        return $byOrder;
    }
}
