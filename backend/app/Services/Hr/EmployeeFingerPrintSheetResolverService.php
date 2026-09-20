<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;
use Illuminate\Validation\ValidationException;

class EmployeeFingerPrintSheetResolverService
{
    public function resolve(array $data): EmployeeFingerPrintSheet
    {
        if (! empty($data['id'])) {
            return EmployeeFingerPrintSheet::with('employee')->findOrFail($data['id']);
        }

        if (empty($data['employee_id']) || empty($data['date'])) {
            throw ValidationException::withMessages([
                'data.id' => ['لا يوجد سجل حضور لهذا اليوم — أرسل employee_id و date'],
            ]);
        }

        $employee = Employee::findOrFail($data['employee_id']);
        $date = $data['date'];

        return EmployeeFingerPrintSheet::firstOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $date],
            [
                'acc_no' => $employee->acc_no,
                'check_in' => '08:00 AM',
                'check_out' => '08:00 AM',
                'hours' => '00:00',
                'iso_date' => $date.'T08:00:00',
                'time_in' => $date.'T08:00:00',
                'time_out' => $date.'T08:00:00',
                'times' => '[]',
            ]
        );
    }
}
