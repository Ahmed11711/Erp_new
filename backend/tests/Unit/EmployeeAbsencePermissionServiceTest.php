<?php

namespace Tests\Unit;

use App\Services\Hr\EmployeeAbsencePermissionService;
use Tests\TestCase;

class EmployeeAbsencePermissionServiceTest extends TestCase
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
}
