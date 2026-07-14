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
            'fingerPrint' => fn ($q) => $q->whereYear('date', $year)->whereMonth('date', $month),
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
        if ($fingerprints->isNotEmpty()) {
            $fingerResult = $this->fingerSheetDifference($employee, $fingerprints, $month, $year);
            if ($fingerResult <= 0) {
                $absenceSub = abs($fingerResult);
            } else {
                $extraHours = $fingerResult;
            }
        }

        $totalMerit = $calcSalary + $incentives + $suits + $rewards + $extraHours;
        $totalSub = round($rival + $absenceSub + $advancePayment, 2);
        $net = $totalMerit - $totalSub;

        return $this->roundNetToFive($net);
    }

    /**
     * @param  Collection<int, EmployeeFingerPrintSheet>  $fingerprints
     */
    private function fingerSheetDifference(Employee $employee, Collection $fingerprints, int $month, int $year): float
    {
        $fixedSalary = (float) $employee->fixed_salary;
        $dayHours = (int) ($employee->working_hours ?: 8);
        $totalHoursPerMonth = $dayHours * 26 * 60;
        $holidayDays = FingerprintHoursHelper::fridayDatesInMonth($year, $month);
        $hourPrice = FingerprintHoursHelper::baseHourPrice($fixedSalary, $dayHours);

        $actualTotalMinutesPerMonth = 0;
        $tableCount = 0;

        foreach ($fingerprints as $sheet) {
            $tableCount++;
            $date = $sheet->date instanceof \DateTimeInterface
                ? $sheet->date->format('Y-m-d')
                : (string) $sheet->date;
            $holiday = in_array($date, $holidayDays, true);

            $record = [
                'date' => $date,
                'check_in' => $sheet->check_in,
                'check_out' => $sheet->check_out,
                'hours' => $sheet->hours,
                'time_in' => $sheet->time_in,
                'time_out' => $sheet->time_out,
                'hours_permission' => $sheet->hours_permission,
            ];

            if (
                FingerprintHoursHelper::isFullDayPermission($sheet->hours_permission, $dayHours)
                && ! $sheet->vacation
                && ! $holiday
            ) {
                $actualTotalMinutesPerMonth += $dayHours * 60;

                continue;
            }

            $dayMinutes = FingerprintHoursHelper::resolveWorkDayMinutes($record, $dayHours);
            $actualTotalMinutesPerMonth += $dayMinutes;

            if ($sheet->is_overTime_removed) {
                $actualTotalMinutesPerMonth -= max($dayMinutes - ($dayHours * 60), 0);
            }

            if ($sheet->hours_permission) {
                $actualTotalMinutesPerMonth += FingerprintHoursHelper::parseTimeToMinutes($sheet->hours_permission);
            }

            if ($sheet->absence_deduction) {
                $deductionDays = (float) $sheet->absence_deduction;
                $actualTotalMinutesPerMonth -= (int) round($dayHours * 60 * max($deductionDays - 1, 0));
            }
        }

        if ($tableCount > 0) {
            $actualTotalMinutesPerMonth -= ($tableCount - count($holidayDays) - 26) * $dayHours * 60;
        }

        $actualHours = FingerprintHoursHelper::minutesToTime($actualTotalMinutesPerMonth);
        $totalHours = FingerprintHoursHelper::minutesToTime($totalHoursPerMonth);

        return $this->calcSalaryDifference($actualHours, $hourPrice, $dayHours, $totalHours, $fixedSalary);
    }

    private function calcSalaryDifference(
        string $empActualHours,
        float $hourPrice,
        int $dayHours,
        string $totalHours,
        float $fixedSalary
    ): float {
        $actualMinutes = FingerprintHoursHelper::parseTimeToMinutes($empActualHours);
        $totalActualHoursSalary = $hourPrice * ($actualMinutes / 60);
        if ($actualMinutes !== 0) {
            $totalActualHoursSalary += $hourPrice * ($dayHours * 4);
        }

        $totalMinutes = FingerprintHoursHelper::parseTimeToMinutes($totalHours);
        if ($actualMinutes > $totalMinutes) {
            $totalActualHoursSalary = $fixedSalary + ((($actualMinutes - $totalMinutes) / 60) * $hourPrice * 1.5);
        }

        return $totalActualHoursSalary - $fixedSalary;
    }

    private function roundNetToFive(float $net): float
    {
        if ($net <= 0) {
            return max(0, $net);
        }

        return ceil($net / 5) * 5;
    }
}
