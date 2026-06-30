<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;

class EmployeeAbsencePermissionService
{
    public function defaultShiftCheckOut(int $dayHours): string
    {
        $endHour24 = 8 + $dayHours;
        $period = $endHour24 >= 12 ? 'PM' : 'AM';
        $h12 = $endHour24 % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }

        return sprintf('%02d:00 %s', $h12, $period);
    }

    public function applyFullDayPermission(int $employeeId, string $date): EmployeeFingerPrintSheet
    {
        $employee = Employee::findOrFail($employeeId);
        $dayHours = (int) ($employee->working_hours ?: 8);
        $hoursPermission = sprintf('%02d:00', $dayHours);
        $checkOut = $this->defaultShiftCheckOut($dayHours);

        $sheet = app(EmployeeFingerPrintSheetResolverService::class)->resolve([
            'employee_id' => $employeeId,
            'date' => $date,
        ]);

        $audit = app(EmployeeFingerPrintSheetAuditService::class);
        $payload = [
            'hours_permission' => $hoursPermission,
            'check_in' => '08:00 AM',
            'check_out' => $checkOut,
            'hours' => $hoursPermission,
        ];
        $changes = $audit->diffModel($sheet, $payload, array_keys($payload));

        $sheet->fill($payload);
        $sheet->save();
        $audit->log($sheet->id, 'إذن — مراجعة الغياب', $changes);

        return $sheet;
    }
}
