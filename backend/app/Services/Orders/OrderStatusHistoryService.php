<?php

namespace App\Services\Orders;

use App\Models\OrderStatusHistory;

final class OrderStatusHistoryService
{
    public static bool $suppressAutoRecord = false;

    public static ?string $pendingReason = null;

    public function record(
        int $orderId,
        ?string $oldStatus,
        string $newStatus,
        ?string $reason,
        ?int $changedBy,
    ): OrderStatusHistory {
        return OrderStatusHistory::create([
            'order_id' => $orderId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);
    }
}
