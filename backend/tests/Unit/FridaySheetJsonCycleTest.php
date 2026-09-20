<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class FridaySheetJsonCycleTest extends TestCase
{
    public function test_unsetting_employee_on_fingerprint_allows_json_encode(): void
    {
        $employee = new Employee(['name' => 'محاسب شحن']);
        $sheet = new EmployeeFingerPrintSheet([
            'date' => '2026-02-27',
            'check_in' => '08:00 AM',
            'check_out' => '04:00 PM',
            'hours' => '08:00',
        ]);
        $employee->setRelation('fingerPrint', new Collection([$sheet]));
        $sheet->setRelation('employee', $employee);
        $sheet->unsetRelation('employee');

        $json = json_encode($employee);
        $this->assertIsString($json);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertSame('2026-02-27', $decoded['finger_print'][0]['date']);
        $this->assertArrayNotHasKey('employee', $decoded['finger_print'][0]);
    }
}
