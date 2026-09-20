<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;
use Illuminate\Support\Collection;

/**
 * حساب صافي الراتب الشهري — مطابق لمنطق payroll.component mapEmployeeMonthRow.
 */
class EmployeePayrollNetSalaryService
{
    public function calculateNetSalary(Employee $employee, int $month, int $year): float
    {
        $employee->loadMissing([
            'merits' => fn ($q) => $q->where('month', $month)->where('year', $year),
            'subtraction' => fn ($q) => $q->where('month', $month)->where('year', $year)
                ->where(function ($query) {
                    $query->where('type', '!=', 'غياب')
                        ->orWhere(function ($sub) {
                            $sub->where('type', 'غياب')->whereNotNull('absence_status');
                        });
                }),
            'advance_payment' => fn ($q) => $q->where('month', $month)->where('year', $year),
            'fingerPrint' => fn ($q) => FingerprintHoursHelper::applyPeriod($q, $year, $month),
        ]);

        $fixedSalary = (float) $employee->fixed_salary;
        $calcSalary = $fixedSalary;
        $incentives = 0.0;
        $suits = 0.0;
        $rewards = 0.0;
        $rival = 0.0;
        $absenceSub = 0.0;
        $advancePayment = 0.0;
        $extraHours = 0.0;

        foreach ($employee->merits as $merit) {
            match ($merit->type) {
                'حوافز' => $incentives += (float) $merit->amount,
                'بدلات' => $suits += (float) $merit->amount,
                'مكافئات' => $rewards += (float) $merit->amount,
                'الراتب المتغير' => $calcSalary += (float) $merit->amount,
                default => null,
            };
        }

        foreach ($employee->subtraction as $sub) {
            if ($sub->type === 'خصومات') {
                $rival += (float) $sub->amount;
            }
            if ($sub->type === 'غياب') {
                $absenceSub += round(((float) $sub->amount) * ($fixedSalary / 30), 2);
            }
        }

        foreach ($employee->advance_payment as $adv) {
            if ($adv->type === 'سلف') {
                $advancePayment += (float) $adv->amount;
            }
        }

        /** @var Collection<int, EmployeeFingerPrintSheet> $fingerprints */
        $fingerprints = $employee->fingerPrint;
        $period = FingerprintHoursHelper::resolvePeriod($year, $month);
        $fingerResult = $this->fingerSheetTotals($employee, $fingerprints, $period['date_from'], $period['date_to']);
        $extraHours = $fingerResult['overtime'];
        if ($fingerResult['absence'] > 0 || $fingerResult['late'] > 0) {
            $absenceSub += $fingerResult['absence'] + $fingerResult['late'];
        }

        $totalMerit = $calcSalary + $incentives + $suits + $rewards + $extraHours;
        $totalSub = round($rival + $absenceSub + $advancePayment, 2);
        $net = $totalMerit - $totalSub;

        return round($net, 2);
    }

    /**
     * @param  Collection<int, EmployeeFingerPrintSheet>  $fingerprints
     * @return array{overtime:float, late:float, absence:float}
     */
    private function fingerSheetTotals(Employee $employee, Collection $fingerprints, string $dateFrom, string $dateTo): array
    {
        $overtime = 0.0;
        $late = 0.0;
        $absence = 0.0;
        if ($fingerprints->isEmpty()) {
            return ['overtime' => 0.0, 'late' => 0.0, 'absence' => 0.0];
        }

        $fixedSalary = (float) $employee->fixed_salary;
        $dayHours = (int) ($employee->working_hours ?: 8);
        $holidayDays = FingerprintHoursHelper::fridayDatesInRange($dateFrom, $dateTo);
        $byDate = [];
        foreach ($fingerprints as $sheet) {
            $date = $sheet->date instanceof \DateTimeInterface
                ? $sheet->date->format('Y-m-d')
                : substr((string) $sheet->date, 0, 10);
            $byDate[$date] = $sheet;
        }

        foreach (FingerprintHoursHelper::datesInRange($dateFrom, $dateTo) as $date) {
            $holiday = in_array($date, $holidayDays, true);
            $sheet = $byDate[$date] ?? null;
            $record = $sheet ? [
                'date' => $date,
                'check_in' => $sheet->check_in,
                'check_out' => $sheet->check_out,
                'hours' => $sheet->hours,
                'time_in' => $sheet->time_in,
                'time_out' => $sheet->time_out,
                'hours_permission' => $sheet->hours_permission,
                'absence_deduction' => $sheet->absence_deduction,
                'is_overTime_removed' => $sheet->is_overTime_removed,
            ] : [
                'date' => $date,
                'check_in' => '08:00 AM',
                'check_out' => '08:00 AM',
                'hours' => '00:00',
                'hours_permission' => null,
                'absence_deduction' => null,
            ];
            $vacation = $sheet?->vacation;
            $score = FingerprintHoursHelper::scoreAttendanceDay(
                $record,
                $fixedSalary,
                $dayHours,
                $holiday,
                $vacation
            );
            if ($score['kind'] === 'overtime' && empty($record['is_overTime_removed'])) {
                $overtime += $score['amount'];
            } elseif ($score['kind'] === 'late') {
                $late += $score['amount'];
            } elseif ($score['kind'] === 'absent') {
                $absence += $score['amount'];
            }
        }

        return [
            'overtime' => round($overtime, 2),
            'late' => round($late, 2),
            'absence' => round($absence, 2),
        ];
    }
}
