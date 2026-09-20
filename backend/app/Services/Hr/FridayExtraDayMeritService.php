<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeFingerPrintSheet;
use App\Models\EmployeeMerits;
use App\Models\EmployeeMonthPaid;

/**
 * حضور يوم الجمعة → معادلة الجمعة أو مكافأة أو بدون حافز.
 *
 * أقل من ساعات اليوم: ساعات إضافي (سعر الساعة × 2)
 * يساوي ساعات اليوم: يوم عادي (الراتب ÷ 30)
 * أكثر من ساعات اليوم: يوم عادي + ساعات إضافي الزيادة (سعر الساعة × 2)
 */
class FridayExtraDayMeritService
{
    public const REWARD_EXTRA_DAY = 'extra_day';

    public const REWARD_BONUS = 'bonus';

    public const REWARD_NONE = 'none';

    /**
     * مزامنة حافز الجمعة دون الإبقاء على علاقة employee داخل السجل.
     * لو الموظف محمّل معه fingerPrint، ترك العلاقة يسبب JSON دائري يطيّح PHP على ويندوز.
     *
     * @param  array{reward_type?: string, bonus_amount?: float|int|string|null, bonus_reason?: string|null}  $options
     */
    public function syncForEmployeeSheet(Employee $employee, EmployeeFingerPrintSheet $sheet, array $options = []): void
    {
        $sheet->setRelation('employee', $employee);
        try {
            $this->syncForSheet($sheet, $options);
        } finally {
            $sheet->unsetRelation('employee');
        }
    }

    /**
     * @param  array{reward_type?: string, bonus_amount?: float|int|string|null, bonus_reason?: string|null}  $options
     */
    public function syncForSheet(EmployeeFingerPrintSheet $sheet, array $options = []): void
    {
        $date = $sheet->date instanceof \DateTimeInterface
            ? $sheet->date->format('Y-m-d')
            : (string) $sheet->date;

        if (! FingerprintHoursHelper::isFridayDate($date)) {
            return;
        }

        $year = (int) substr($date, 0, 4);
        $month = (int) substr($date, 5, 2);
        $employeeId = (int) $sheet->employee_id;
        $fullReason = FingerprintHoursHelper::fridayExtraDayReason($date);
        $partialReason = FingerprintHoursHelper::fridayPartialReason($date);

        $existingFull = $this->findMerit($employeeId, $month, $year, 'حوافز', $fullReason);
        $existingPartial = $this->findMerit($employeeId, $month, $year, 'حوافز', $partialReason);
        $existingBonus = $this->findFridayBonusMerit($employeeId, $month, $year, $date);

        if (! FingerprintHoursHelper::hasFridayAttendance([
            'check_in' => $sheet->check_in,
            'check_out' => $sheet->check_out,
            'hours' => $sheet->hours,
        ])) {
            $existingFull?->delete();
            $existingPartial?->delete();
            $existingBonus?->delete();

            return;
        }

        if (EmployeeMonthPaid::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists()) {
            return;
        }

        $rewardType = (string) ($options['reward_type'] ?? self::REWARD_EXTRA_DAY);
        if (! in_array($rewardType, [self::REWARD_EXTRA_DAY, self::REWARD_BONUS, self::REWARD_NONE], true)) {
            $rewardType = self::REWARD_EXTRA_DAY;
        }

        // استيراد/تعديل بدون تحديد نوع: أبقِ المكافأة اليدوية إن وُجدت
        if (! array_key_exists('reward_type', $options) && $existingBonus) {
            $existingFull?->delete();
            $existingPartial?->delete();

            return;
        }

        if ($rewardType === self::REWARD_NONE) {
            $existingFull?->delete();
            $existingPartial?->delete();
            $existingBonus?->delete();

            return;
        }

        if ($rewardType === self::REWARD_BONUS) {
            $existingFull?->delete();
            $existingPartial?->delete();
            $amount = round((float) ($options['bonus_amount'] ?? 0), 2);
            if ($amount <= 0) {
                $existingBonus?->delete();

                return;
            }

            $note = trim((string) ($options['bonus_reason'] ?? ''));
            $reason = FingerprintHoursHelper::fridayBonusReason($date, $note !== '' ? $note : null);

            $this->upsertMerit($existingBonus, [
                'employee_id' => $employeeId,
                'month' => $month,
                'year' => $year,
                'type' => 'مكافئات',
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            return;
        }

        // معادلة الجمعة
        $existingBonus?->delete();

        $employee = $sheet->relationLoaded('employee')
            ? $sheet->employee
            : Employee::query()->find($employeeId);

        if (! $employee) {
            return;
        }

        $dayHours = (int) ($employee->working_hours ?: 8);
        $workedHours = FingerprintHoursHelper::parseTimeToMinutes((string) ($sheet->hours ?? '00:00')) / 60;
        $amount = FingerprintHoursHelper::fridayAttendanceMeritAmount(
            (float) $employee->fixed_salary,
            $dayHours,
            $workedHours
        );

        if ($amount <= 0) {
            $existingFull?->delete();
            $existingPartial?->delete();

            return;
        }

        $fullDay = FingerprintHoursHelper::isFridayFullDay($workedHours, $dayHours);
        $reason = FingerprintHoursHelper::fridayAttendanceReason($date, $fullDay);

        if ($fullDay) {
            $existingPartial?->delete();
            $this->upsertMerit($existingFull, [
                'employee_id' => $employeeId,
                'month' => $month,
                'year' => $year,
                'type' => 'حوافز',
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            return;
        }

        $existingFull?->delete();
        $this->upsertMerit($existingPartial, [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'type' => 'حوافز',
            'amount' => $amount,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>|EmployeeFingerPrintSheet>  $entries
     */
    public function syncForEntries(iterable $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry instanceof EmployeeFingerPrintSheet) {
                $this->syncForSheet($entry);

                continue;
            }

            $employeeId = (int) ($entry['employee_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if ($employeeId <= 0 || $date === '' || ! FingerprintHoursHelper::isFridayDate($date)) {
                continue;
            }

            $sheet = EmployeeFingerPrintSheet::query()
                ->with('employee')
                ->where('employee_id', $employeeId)
                ->where('date', $date)
                ->first();

            if ($sheet) {
                $this->syncForSheet($sheet);
            }
        }
    }

    private function findMerit(int $employeeId, int $month, int $year, string $type, string $reason): ?EmployeeMerits
    {
        return EmployeeMerits::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('type', $type)
            ->where('reason', $reason)
            ->first();
    }

    /** يلتقط مكافأة الجمعة حتى لو السبب القديم يحتوي ملاحظة بعد التاريخ */
    private function findFridayBonusMerit(int $employeeId, int $month, int $year, string $date): ?EmployeeMerits
    {
        return EmployeeMerits::query()
            ->where('employee_id', $employeeId)
            ->where('month', $month)
            ->where('year', $year)
            ->where('type', 'مكافئات')
            ->where(function ($q) use ($date) {
                $q->where('reason', 'like', "مكافأة حضور الجمعة ({$date})%")
                    ->orWhere('reason', 'like', "%({$date})%");
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertMerit(?EmployeeMerits $existing, array $payload): void
    {
        if ($existing) {
            $existing->amount = $payload['amount'];
            $existing->reason = $payload['reason'];
            $existing->type = $payload['type'];
            $existing->save();

            return;
        }

        EmployeeMerits::create($payload);
    }
}
