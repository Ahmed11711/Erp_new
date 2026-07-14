<?php

namespace App\Services\Hr;

/**
 * حساب ساعات اليوم من سجل البصمة — مطابق لمنطق resolveWorkDayHours في الواجهة.
 */
class FingerprintHoursHelper
{
    public const WORKING_DAYS_PER_MONTH = 26;

    public const OVERTIME_DEDUCTION_MULTIPLIER = 1.5;

    public static function baseHourPrice(float $fixedSalary, int $dayHours): float
    {
        return $fixedSalary / (self::WORKING_DAYS_PER_MONTH * $dayHours);
    }

    public static function overtimeDeductionRate(float $fixedSalary, int $dayHours): float
    {
        return self::baseHourPrice($fixedSalary, $dayHours) * self::OVERTIME_DEDUCTION_MULTIPLIER;
    }

    public static function deductionMultiplier(mixed $absenceDeduction): float
    {
        if ($absenceDeduction !== null && $absenceDeduction !== '') {
            return (float) $absenceDeduction;
        }

        return self::OVERTIME_DEDUCTION_MULTIPLIER;
    }

    public static function parseTimeToMinutes(string $time): int
    {
        $time = trim($time);
        if ($time === '' || $time === '00:00') {
            return 0;
        }
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }

    public static function minutesToTime(int $minutes): string
    {
        $h = intdiv(max($minutes, 0), 60);
        $m = max($minutes, 0) % 60;

        return sprintf('%02d:%02d', $h, $m);
    }

    public static function isFullDayPermission(?string $hoursPermission, int $dayHours): bool
    {
        if ($hoursPermission === null || $hoursPermission === '') {
            return false;
        }

        return self::parseTimeToMinutes($hoursPermission) >= ($dayHours * 60);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function resolveWorkDayMinutes(array $record, int $dayHours): int
    {
        if (self::isFullDayPermission($record['hours_permission'] ?? null, $dayHours)) {
            return $dayHours * 60;
        }

        $date = $record['date'] ?? null;
        $checkIn = $record['check_in'] ?? null;
        $checkOut = $record['check_out'] ?? null;
        if ($date && $checkIn && $checkOut && $checkIn !== $checkOut) {
            $fromCheck = self::minutesFromCheckInOut((string) $date, (string) $checkIn, (string) $checkOut);
            if ($fromCheck > 0) {
                return $fromCheck;
            }
        }

        $fromInOut = self::minutesFromIsoPair($record['time_in'] ?? null, $record['time_out'] ?? null);
        if ($fromInOut > 0) {
            return $fromInOut;
        }

        return self::parseTimeToMinutes((string) ($record['hours'] ?? '00:00'));
    }

    public static function minutesFromCheckInOut(string $dateStr, string $checkIn12h, string $checkOut12h): int
    {
        $in = self::parseLocalDateTime($dateStr, $checkIn12h);
        $out = self::parseLocalDateTime($dateStr, $checkOut12h);
        if (! $in || ! $out) {
            return 0;
        }
        if ($out <= $in) {
            $out = $out->modify('+1 day');
        }
        $diff = ($out->getTimestamp() - $in->getTimestamp()) / 60;

        return $diff > 0 ? (int) round($diff) : 0;
    }

    public static function minutesFromIsoPair(?string $timeIn, ?string $timeOut): int
    {
        if (! $timeIn || ! $timeOut) {
            return 0;
        }
        try {
            $in = new \DateTimeImmutable($timeIn);
            $out = new \DateTimeImmutable($timeOut);
        } catch (\Exception) {
            return 0;
        }
        if ($out <= $in) {
            $out = $out->modify('+1 day');
        }
        $diff = ($out->getTimestamp() - $in->getTimestamp()) / 60;

        return $diff > 0 ? (int) round($diff) : 0;
    }

    public static function parseLocalDateTime(string $dateStr, string $time12h): ?\DateTimeImmutable
    {
        $time12h = trim($time12h);
        if ($dateStr === '' || $time12h === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $time12h);
        $timePart = $parts[0] ?? '';
        $period = strtoupper($parts[1] ?? '');
        [$hourRaw, $minuteRaw] = array_pad(explode(':', $timePart), 2, '0');
        $hour = (int) $hourRaw;
        $minute = (int) $minuteRaw;

        if ($period === 'PM' && $hour < 12) {
            $hour += 12;
        } elseif ($period === 'AM' && $hour === 12) {
            $hour = 0;
        }

        $dateParts = explode('-', $dateStr);
        if (count($dateParts) !== 3) {
            return null;
        }

        try {
            return new \DateTimeImmutable(sprintf(
                '%04d-%02d-%02d %02d:%02d:00',
                (int) $dateParts[0],
                (int) $dateParts[1],
                (int) $dateParts[2],
                $hour,
                $minute
            ));
        } catch (\Exception) {
            return null;
        }
    }

    /** @return list<string> */
    public static function fridayDatesInMonth(int $year, int $month): array
    {
        $dates = [];
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $ts = mktime(0, 0, 0, $month, $day, $year);
            if ((int) date('w', $ts) === 5) {
                $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return $dates;
    }
}
