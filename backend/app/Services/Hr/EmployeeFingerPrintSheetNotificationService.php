<?php

namespace App\Services\Hr;

use App\Models\EmployeeFingerPrintSheet;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class EmployeeFingerPrintSheetNotificationService
{
    public function notifyAdmins(EmployeeFingerPrintSheet $sheet, string $actionLabel): void
    {
        $actor = Auth::user();
        if (! $actor || in_array($actor->department, ['Admin', 'admin'], true)) {
            return;
        }

        $sheet->loadMissing('employee');
        $employeeName = $sheet->employee->name ?? 'موظف';
        $note = sprintf('%s — %s — %s', $actionLabel, $employeeName, $sheet->date);

        $admins = User::query()
            ->whereIn('department', ['Admin', 'admin'])
            ->get(['id']);

        foreach ($admins as $admin) {
            Notification::create([
                'send_from' => $actor->id,
                'send_to' => $admin->id,
                'type' => 'كشف حضور',
                'ref' => (string) $sheet->employee_id,
                'order_id' => null,
                'note' => $note,
            ]);
            Cache::forget('user_notifications_'.$admin->id);
        }
    }
}
