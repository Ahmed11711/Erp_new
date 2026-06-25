<?php

namespace App\Services\Processing;

use App\Models\ProcessingOrder;
use Illuminate\Support\Facades\DB;

class ProcessingActivityLogger
{
    public function log(string $subjectType, int $subjectId, string $action, ?array $old = null, ?array $new = null): void
    {
        DB::table('processing_activity_logs')->insert([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function logOrder(ProcessingOrder $order, string $action, ?array $old = null, ?array $new = null): void
    {
        $this->log('processing_order', (int) $order->id, $action, $old, $new);
    }
}
