<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeMerits;
use App\Models\User;
use App\Services\Hr\EmployeePayrollNetSalaryService;
use App\Services\Hr\FingerprintHoursHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollNetSalaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_fixed_salary_with_merits_and_subtractions(): void
    {
        $employee = Employee::create([
            'name' => 'موظف تجريبي',
            'code' => '9001',
            'level' => '1',
            'department' => 'IT',
            'fixed_salary' => 3000,
            'salary_type' => 'ثابت',
        ]);

        $user = User::factory()->create();

        EmployeeMerits::create([
            'employee_id' => $employee->id,
            'type' => 'حوافز',
            'amount' => 200,
            'month' => 5,
            'year' => 2026,
            'user_id' => $user->id,
        ]);

        $net = app(EmployeePayrollNetSalaryService::class)->calculateNetSalary($employee, 5, 2026);

        $this->assertSame(3200.0, $net);
    }

    public function test_friday_dates_in_month(): void
    {
        $fridays = FingerprintHoursHelper::fridayDatesInMonth(2026, 6);
        $this->assertNotEmpty($fridays);
        foreach ($fridays as $date) {
            $this->assertSame(5, (int) date('w', strtotime($date)));
        }
    }
}
