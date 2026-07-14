<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class OrderPrintService
{
    public function __construct(
        protected OrderStatusVisibilityService $orderStatusVisibility,
    ) {
    }

    public function resolveShowInvoiceDate(): bool
    {
        $value = Setting::query()->where('key', 'show_invoice_date')->value('value');

        if ($value === null || $value === '') {
            return true;
        }

        return ! in_array(strtolower((string) $value), ['0', 'false', 'off', 'no'], true);
    }

    /**
     * @param  int[]  $orderIds
     * @return array{orders: Collection<int, Order>, show_invoice_date: bool}
     */
    public function getPrintPayload(array $orderIds, User $user): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), fn (int $id) => $id > 0)));

        if ($orderIds === []) {
            return [
                'orders' => collect(),
                'show_invoice_date' => $this->resolveShowInvoiceDate(),
            ];
        }

        $ordersById = Order::query()
            ->with([
                'order_products.category:id,category_name',
                'order_details.shipping_company:id,name',
                'order_details.shipping_line:id,name',
                'shipping_method:id,name',
            ])
            ->visibleToUser($user)
            ->whereIn('id', $orderIds)
            ->get()
            ->filter(fn (Order $order) => $this->orderStatusVisibility->canViewStatus($user, (string) $order->order_status))
            ->keyBy('id');

        $sorted = collect($orderIds)
            ->map(fn (int $id) => $ordersById->get($id))
            ->filter()
            ->values();

        return [
            'orders' => $sorted,
            'show_invoice_date' => $this->resolveShowInvoiceDate(),
        ];
    }
}
