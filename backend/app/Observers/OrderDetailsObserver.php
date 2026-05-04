<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Services\Accounting\SalesOrderAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * عند ربط شركة شحن / شركة تحصيل يُعاد بناء قيود الفاتورة والدفعة المقدمة لتطابق ذمم الوسيط.
 */
class OrderDetailsObserver
{
    public function saved(OrderDetails $orderDetails): void
    {
        if (! $orderDetails->order_id) {
            return;
        }

        $hasLink = (bool) $orderDetails->shipping_company_id || (bool) $orderDetails->collection_company_id;

        if ($orderDetails->wasRecentlyCreated && ! $hasLink) {
            return;
        }

        if (! $orderDetails->wasRecentlyCreated && ! $orderDetails->wasChanged(['shipping_company_id', 'collection_company_id'])) {
            return;
        }

        $orderId = (int) $orderDetails->order_id;

        DB::afterCommit(function () use ($orderId) {
            $order = Order::with('order_products')->find($orderId);
            if (! $order) {
                return;
            }
            try {
                app(SalesOrderAccountingService::class)->refreshOrderRecognition($order, true);
            } catch (\Throwable $e) {
                Log::error('OrderDetailsObserver: refreshOrderRecognition failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
