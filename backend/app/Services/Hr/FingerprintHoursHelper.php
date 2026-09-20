<?php

namespace App\Services\Hr;

/**
 * حساب ساعات اليوم من سجل البصمة — مطابق لمنطق resolveWorkDayHours في الواجهة.
 */
class FingerprintHoursHelper
{
    public const WORKING_DAYS_PER_MONTH = 26;

    /** يوم تقفيل البصمة — بعده يبدأ شهر التقفيل التالي */
    public const CLOSE_DAY = 25;

    /** أول يوم في دورة البصمة (26 الشهر السابق) */
    public const PERIOD_START_DAY = 26;

    /** أيام العمل لـ«يوم إضافي» فقط — مطابق للواجهة */
    public const EXTRA_DAY_WORKING_DAYS = 30;

    public const OVERTIME_DEDUCTION_MULTIPLIER = 1.5;

    public static function baseHourPrice(float $fixedSalary, int $dayHours): float
    {
        return $fixedSalary / (self::WORKING_DAYS_PER_MONTH * $dayHours);
    }

    public static function overtimeDeductionRate(float $fixedSalary, int $dayHours): float
    {
        return self::baseHourPrice($fixedSalary, $dayHours) * self::OVERTIME_DEDUCTION_MULTIPLIER;
    }

    /** مبلغ يوم إضافي = (الراتب ÷ 30 ÷ ساعات اليوم) × 1.5 × ساعات اليوم */
    public static function extraDayMeritAmount(float $fixedSalary, int $dayHours): float
    {
        $hourlyBase = $fixedSalary / (self::EXTRA_DAY_WORKING_DAYS * max($dayHours, 1));

        return round(max($dayHours, 1) * $hourlyBase * self::OVERTIME_DEDUCTION_MULTIPLIER, 2);
    }

    /** مضاعف ساعة الجمعة = سعر الساعة العادية × 2 */
    public const FRIDAY_HOUR_MULTIPLIER = 2;

    /** يوم الجمعة الكامل = يوم عادي (الراتب ÷ 30) بدون مضاعف 1.5 */
    public static function fridayNormalDayAmount(float $fixedSalary): float
    {
        return round($fixedSalary / self::EXTRA_DAY_WORKING_DAYS, 2);
    }

    /**
     * حافز حضور الجمعة:
     * - أقل من ساعات اليوم: ساعات إضافي فقط (سعر الساعة × 2)
     * - يساوي ساعات اليوم: يوم عادي (الراتب ÷ 30)
     * - أكثر من ساعات اليوم: يوم عادي + ساعات إضافي الزيادة (سعر الساعة × 2)
     */
    public static function fridayAttendanceMeritAmount(
        float $fixedSalary,
        int $dayHours,
        float $workedHours
    ): float {
        $dayHours = max($dayHours, 1);
        $workedHours = max($workedHours, 0);
        if ($workedHours <= 0) {
            return 0.0;
        }

        $extraHourRate = self::baseHourPrice($fixedSalary, $dayHours) * self::FRIDAY_HOUR_MULTIPLIER;

        if ($workedHours + 1e-6 < $dayHours) {
            return round($workedHours * $extraHourRate, 2);
        }

        $overtimeHours = max($workedHours - $dayHours, 0);

        return round(self::fridayNormalDayAmount($fixedSalary) + ($overtimeHours * $extraHourRate), 2);
    }

    public static function isFridayFullDay(float $workedHours, int $dayHours): bool
    {
        return $workedHours + 1e-6 >= max($dayHours, 1);
    }

    public static function isFridayDate(string $date): bool
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return false;
        }

        return (int) date('w', $ts) === 5;
    }

    public static function fridayExtraDayReason(string $date): string
    {
        return "إضافي يوم كامل ({$date})";
    }

    public static function fridayPartialReason(string $date): string
    {
        return "إضافي جمعة ({$date})";
    }

    public static function fridayBonusReason(string $date, ?string $note = null): string
    {
        $base = "مكافأة حضور الجمعة ({$date})";
        $note = trim((string) $note);
        if ($note === '' || $note === $base) {
            return $base;
        }

        // السبب المخصص يُحفظ مع التاريخ حتى يظهر في كشف الجمعة
        return "{$base}: {$note}";
    }

    public static function fridayAttendanceReason(string $date, bool $fullDay): string
    {
        return $fullDay
            ? self::fridayExtraDayReason($date)
            : self::fridayPartialReason($date);
    }

    /** أيام الشهر لخصم الغياب باليوم */
    public const ABSENCE_DAY_DIVISOR = 30;

    public static function absenceDayAmount(float $fixedSalary, float $days = 1.0): float
    {
        $dayCount = $days > 0 ? $days : 1.0;

        return round(($fixedSalary / self::ABSENCE_DAY_DIVISOR) * $dayCount, 2);
    }

    public static function absenceDayCount(mixed $absenceDeduction): float
    {
        if ($absenceDeduction !== null && $absenceDeduction !== '') {
            $n = (float) $absenceDeduction;
            if ($n > 0) {
                return $n;
            }
        }

        return 1.0;
    }

    /**
     * @param  array{hours?: mixed, check_in?: mixed, hours_permission?: mixed}  $record
     */
    public static function isAbsentDay(array $record, mixed $vacation = null, bool $holiday = false): bool
    {
        if ($holiday) {
            return false;
        }
        if ($vacation === true || $vacation === 1 || $vacation === '1') {
            return false;
        }
        if (! empty($record['hours_permission'])) {
            return false;
        }
        $hours = (string) ($record['hours'] ?? '00:00');
        $checkIn = (string) ($record['check_in'] ?? '08:00 AM');

        return $hours === '00:00' && ($checkIn === '' || $checkIn === '08:00 AM');
    }

    /**
     * @param  array{check_in?: mixed, check_out?: mixed, hours?: mixed}  $record
     */
    public static function hasFridayAttendance(array $record): bool
    {
        $checkIn = (string) ($record['check_in'] ?? '');
        $checkOut = (string) ($record['check_out'] ?? '');
        if ($checkIn === '' || $checkOut === '' || $checkIn === $checkOut) {
            return false;
        }

        return self::parseTimeToMinutes((string) ($record['hours'] ?? '00:00')) > 0;
    }

    public static function deductionMultiplier(mixed $absenceDeduction): float
    {
        if ($absenceDeduction !== null && $absenceDeduction !== '') {
            return (float) $absenceDeduction;
        }

        return self::OVERTIME_DEDUCTION_MULTIPLIER;
    }

    /**
     * حساب حافز/خصم اليوم من البصمة — مطابق annotateAttendanceDay في الواجهة.
     *
     * @param  array<string, mixed>  $record
     * @return array{
     *     kind:string,
     *     amount:float,
     *     minutes:int,
     *     absence_days:float,
     *     absence_day:bool,
     *     friday_work:bool
     * }
     */
    public static function scoreAttendanceDay(array $record, float $fixedSalary, int $dayHours, bool $holiday = false, mixed $vacation = null): array
    {
        $dayHours = max($dayHours, 1);
        $hourPrice = self::baseHourPrice($fixedSalary, $dayHours);
        $vacation = $vacation === true || $vacation === 1 || $vacation === '1';

        if (self::isFullDayPermission($record['hours_permission'] ?? null, $dayHours) && ! $vacation && ! $holiday) {
            return [
                'kind' => 'permission',
                'amount' => 0.0,
                'minutes' => $dayHours * 60,
                'absence_days' => 0.0,
                'absence_day' => false,
                'friday_work' => false,
            ];
        }

        if ($vacation) {
            return [
                'kind' => 'vacation',
                'amount' => 0.0,
                'minutes' => 0,
                'absence_days' => 0.0,
                'absence_day' => false,
                'friday_work' => false,
            ];
        }

        $workedMins = self::resolveWorkDayMinutes($record, $dayHours);
        $fridayWork = $holiday && self::hasFridayAttendance($record);
        $absentDay = ! $fridayWork && self::isAbsentDay($record, $vacation, $holiday);

        if ($absentDay) {
            $days = self::absenceDayCount($record['absence_deduction'] ?? null);

            return [
                'kind' => 'absent',
                'amount' => self::absenceDayAmount($fixedSalary, $days),
                'minutes' => $dayHours * 60,
                'absence_days' => $days,
                'absence_day' => true,
                'friday_work' => false,
            ];
        }

        $minutesForMonth = $fridayWork ? 0 : $workedMins;
        if (! $fridayWork && ! empty($record['is_overTime_removed'])) {
            $minutesForMonth -= $workedMins - ($dayHours * 60);
        }
        if (! $fridayWork && ! empty($record['hours_permission'])) {
            $minutesForMonth += self::parseTimeToMinutes((string) $record['hours_permission']);
        }

        if ($fridayWork) {
            $workedHours = $workedMins / 60;

            return [
                'kind' => 'friday',
                'amount' => self::fridayAttendanceMeritAmount($fixedSalary, $dayHours, $workedHours),
                'minutes' => $minutesForMonth,
                'absence_days' => 0.0,
                'absence_day' => false,
                'friday_work' => true,
            ];
        }

        $hoursDifference = $workedMins - ($dayHours * 60);
        if ($hoursDifference >= 0) {
            return [
                'kind' => 'overtime',
                'amount' => ($hoursDifference / 60) * $hourPrice * self::OVERTIME_DEDUCTION_MULTIPLIER,
                'minutes' => $minutesForMonth,
                'absence_days' => 0.0,
                'absence_day' => false,
                'friday_work' => false,
            ];
        }

        $rate = self::deductionMultiplier($record['absence_deduction'] ?? null);
        $salary = ($hoursDifference / 60) * $hourPrice * $rate;
        if (! empty($record['hours_permission'])) {
            $salary += (self::parseTimeToMinutes((string) $record['hours_permission']) / 60) * $hourPrice * $rate;
        }

        return [
            'kind' => 'late',
            'amount' => abs($salary),
            'minutes' => $minutesForMonth,
            'absence_days' => 0.0,
            'absence_day' => false,
            'friday_work' => false,
        ];
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

    public const DEFAULT_SHIFT_START = '08:00 AM';

    /**
     * إعادة اليوم لقالب الغياب حتى يُحسب خصم يوم (راتب÷30) وليس حضور 8 ساعات.
     *
     * @return array{
     *     hours_permission: null,
     *     absence_deduction: null,
     *     check_in: string,
     *     check_out: string,
     *     hours: string,
     *     time_in: string,
     *     time_out: string,
     *     iso_date: string,
     *     times: string,
     *     reviewed: bool
     * }
     */
    public static function absentDayPlaceholderPayload(string $date): array
    {
        $iso = $date.'T08:00:00';

        return [
            'hours_permission' => null,
            'absence_deduction' => null,
            'check_in' => self::DEFAULT_SHIFT_START,
            'check_out' => self::DEFAULT_SHIFT_START,
            'hours' => '00:00',
            'time_in' => $iso,
            'time_out' => $iso,
            'iso_date' => $iso,
            'times' => '[]',
            'reviewed' => false,
        ];
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
        // نفس الوقت = 0 ساعات (بصمة واحدة) — لا تُحسب 24 ساعة
        if ($out == $in) {
            return 0;
        }
        if ($out < $in) {
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
        // نفس الوقت = 0 ساعات (بصمة واحدة) — لا تُحسب 24 ساعة
        if ($out == $in) {
            return 0;
        }
        if ($out < $in) {
            $out = $out->modify('+1 day');
        }
        $diff = ($out->getTimestamp() - $in->getTimestamp()) / 60;

        return $diff > 0 ? (int) round($diff) : 0;
    }

    /**
     * دقائق تُطرح لتطبيع أيام التقويم الزائدة فوق 26 يوم عمل.
     * لا نضيف ساعات وهمية عندما يكون شيت البصمة ناقصاً (غياب).
     */
    public static function monthHoursPaddingMinutes(int $tableDayCount, int $holidayCount, int $dayHours): int
    {
        $excessDays = $tableDayCount - $holidayCount - self::WORKING_DAYS_PER_MONTH;
        if ($excessDays <= 0) {
            return 0;
        }

        return $excessDays * $dayHours * 60;
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

    /**
     * شهر التقفيل M = من 26 الشهر السابق إلى 25 الشهر M.
     *
     * @return array{year:int,month:int,date_from:string,date_to:string}
     */
    public static function periodForMonth(int $year, int $month): array
    {
        $dateTo = sprintf('%04d-%02d-%02d', $year, $month, self::CLOSE_DAY);
        $previous = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('-1 month');
        $dateFrom = sprintf(
            '%04d-%02d-%02d',
            (int) $previous->format('Y'),
            (int) $previous->format('n'),
            self::PERIOD_START_DAY
        );

        return [
            'year' => $year,
            'month' => $month,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * تاريخ اليوم 26 فما فوق يتبع شهر التقفيل التالي.
     *
     * @return array{year:int,month:int}
     */
    public static function payrollMonthForDate(string $date): array
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', substr($date, 0, 10));
        if (! $parsed) {
            $now = new \DateTimeImmutable('today');

            return ['year' => (int) $now->format('Y'), 'month' => (int) $now->format('n')];
        }

        if ((int) $parsed->format('j') <= self::CLOSE_DAY) {
            return [
                'year' => (int) $parsed->format('Y'),
                'month' => (int) $parsed->format('n'),
            ];
        }

        $next = $parsed->modify('first day of next month');

        return [
            'year' => (int) $next->format('Y'),
            'month' => (int) $next->format('n'),
        ];
    }

    /**
     * @return array{year:int,month:int,date_from:string,date_to:string}
     */
    public static function resolvePeriod(
        int $year,
        int $month,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $period = self::periodForMonth($year, $month);
        if ($dateFrom && $dateTo) {
            $period['date_from'] = substr($dateFrom, 0, 10);
            $period['date_to'] = substr($dateTo, 0, 10);
        }

        return $period;
    }

    public static function applyDateRange($query, string $from, string $to, string $column = 'date')
    {
        // مقارنة مباشرة (بدون DATE()) حتى يستفيد الاستعلام من فهرس (employee_id, date).
        return $query->where($column, '>=', substr($from, 0, 10))
            ->where($column, '<=', substr($to, 0, 10));
    }

    public static function applyPeriod($query, int $year, int $month, string $column = 'date')
    {
        $period = self::periodForMonth($year, $month);

        return self::applyDateRange($query, $period['date_from'], $period['date_to'], $column);
    }

    /** @return list<string> */
    public static function datesInRange(string $from, string $to): array
    {
        $dates = [];
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', substr($from, 0, 10));
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', substr($to, 0, 10));
        if (! $start || ! $end || $start > $end) {
            return [];
        }

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $dates[] = $cursor->format('Y-m-d');
        }

        return $dates;
    }

    /** @return list<string> */
    public static function fridayDatesInRange(string $from, string $to): array
    {
        $dates = [];
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', substr($from, 0, 10));
        $end = \DateTimeImmutable::createFromFormat('Y-m-d', substr($to, 0, 10));
        if (! $start || ! $end || $start > $end) {
            return [];
        }

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            if ((int) $cursor->format('w') === 5) {
                $dates[] = $cursor->format('Y-m-d');
            }
        }

        return $dates;
    }

    /** @return list<string> */
    public static function fridayDatesInMonth(int $year, int $month): array
    {
        $period = self::periodForMonth($year, $month);

        return self::fridayDatesInRange($period['date_from'], $period['date_to']);
    }
}
