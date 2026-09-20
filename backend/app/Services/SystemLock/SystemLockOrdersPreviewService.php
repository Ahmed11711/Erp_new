<?php

namespace App\Services\SystemLock;

use App\Models\Order;
use App\Models\OrderSource;
use App\Models\ShippingCompany;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\shippingline;
use App\Services\Orders\OrderStatusVisibilityService;
use Illuminate\Support\Collection;
use Throwable;

class SystemLockOrdersPreviewService
{
    public const LIMIT = 10;

    public function __construct(
        protected OrderStatusVisibilityService $orderStatusVisibility,
    ) {}

    /**
     * آخر 10 طلبات بنفس شكل قائمة الطلبات، للعرض أثناء قفل النظام.
     *
     * @return array{
     *   data: mixed,
     *   total: int,
     *   per_page: int,
     *   current_page: int,
     *   last_page: int,
     *   from: int,
     *   to: int,
     *   lookups: array<string, mixed>
     * }
     */
    public function payload(User $user): array
    {
        $orders = $this->latestOrders($user);
        $this->attachCustomerOrderCounts($orders);
        $count = $orders->count();

        return [
            'data' => $orders->values(),
            'total' => $count,
            'per_page' => self::LIMIT,
            'current_page' => 1,
            'last_page' => 1,
            'from' => $count > 0 ? 1 : 0,
            'to' => $count,
            'lookups' => $this->lookups(),
        ];
    }

    /**
     * @return Collection<int, Order>
     */
    private function latestOrders(User $user): Collection
    {
        $query = Order::query()->whereNull('offer_id');

        if (trim((string) ($user->department ?? '')) !== 'Admin') {
            $userId = (int) $user->id;
            $query->where(function ($q) use ($userId) {
                $q->whereNull('private_order')
                    ->orWhereHas('notifications', function ($notificationQuery) use ($userId) {
                        $notificationQuery->where('send_to', $userId);
                    });
            });
        }

        $this->orderStatusVisibility->applySearchScope($query, $user);

        return $query->with([
            'order_details.shipping_line',
            'order_details.shipping_company',
            'shipping_method',
            'shopifyReviewer:id,name',
            'order_products.category:id,category_name',
            'notifications' => function ($q) use ($user) {
                $q->where('send_to', $user->id)
                    ->select('id', 'send_from', 'send_to', 'type', 'ref', 'note', 'order_id', 'notification_number', 'created_at', 'is_read');
            },
            'notifications.sender:id,name',
        ])
            ->withCount([
                'notifications as review_notifications_count' => function ($q) use ($user) {
                    $q->where('type', 'مراجعة')
                        ->where('send_from', $user->id);
                },
            ])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function attachCustomerOrderCounts(Collection $orders): void
    {
        $phones = $orders->pluck('customer_phone_1')->filter()->unique()->values();
        if ($phones->isEmpty()) {
            return;
        }

        $counts = Order::query()
            ->select('customer_phone_1')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->whereIn('customer_phone_1', $phones)
            ->groupBy('customer_phone_1')
            ->pluck('aggregate_count', 'customer_phone_1');

        foreach ($orders as $row) {
            $row->setAttribute(
                'customer_orders_count',
                (int) ($counts[$row->customer_phone_1] ?? 0)
            );
        }
    }

    /**
     * @return array{
     *   companies: mixed,
     *   order_sources: mixed,
     *   shipping_ways: mixed,
     *   shipping_lines: mixed
     * }
     */
    private function lookups(): array
    {
        return [
            'companies' => $this->safeList(fn () => ShippingCompany::query()->select('id', 'name', 'type')->orderBy('name')->get()),
            'order_sources' => $this->safeList(fn () => OrderSource::query()->select('id', 'name')->orderBy('name')->get()),
            'shipping_ways' => $this->safeList(fn () => ShippingMethod::query()->select('id', 'name')->orderBy('name')->get()),
            'shipping_lines' => $this->safeList(fn () => shippingline::query()->select('id', 'name')->orderBy('name')->get()),
        ];
    }

    /**
     * @param  callable(): mixed  $resolver
     */
    private function safeList(callable $resolver): array
    {
        try {
            $rows = $resolver();
        } catch (Throwable $e) {
            return [];
        }

        if ($rows instanceof Collection) {
            return $rows->values()->all();
        }

        return is_array($rows) ? $rows : [];
    }
}
