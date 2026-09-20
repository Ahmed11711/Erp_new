<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\User;
use App\Services\Hr\EmployeeAbsencePermissionService;
use App\Services\Hr\EmployeeFingerPrintSheetNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeFingerPrintSheetNotificationServiceTest extends TestCase
{
    public function test_default_shift_checkout_for_eight_hour_day(): void
    {
        $service = new EmployeeAbsencePermissionService();
        $this->assertSame('04:00 PM', $service->defaultShiftCheckOut(8));
    }

    public function test_default_shift_checkout_for_nine_hour_day(): void
    {
        $service = new EmployeeAbsencePermissionService();
        $this->assertSame('05:00 PM', $service->defaultShiftCheckOut(9));
    }

    public function test_hr_notification_can_be_created_without_order_id(): void
    {
        $from = User::query()->value('id') ?? 1;
        $to = User::query()->whereIn('department', ['Admin', 'admin'])->value('id') ?? $from;

        $notification = Notification::create([
            'send_from' => $from,
            'send_to' => $to,
            'type' => 'كشف حضور',
            'ref' => '18',
            'order_id' => null,
            'note' => 'تعديل حضور/انصراف — موظف تجريبي — 2026-06-29',
        ]);

        $this->assertSame('كشف حضور', $notification->type);
        $this->assertNull($notification->order_id);
        $notification->delete();
    }
}
