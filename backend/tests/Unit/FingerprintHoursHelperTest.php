<?php

namespace Tests\Unit;

use App\Services\Hr\FingerprintHoursHelper;
use PHPUnit\Framework\TestCase;

class FingerprintHoursHelperTest extends TestCase
{
    public function test_minutes_from_iso_pair_is_zero_when_in_equals_out(): void
    {
        $this->assertSame(
            0,
            FingerprintHoursHelper::minutesFromIsoPair('2026-07-01T10:13:00', '2026-07-01T10:13:00')
        );
    }

    public function test_minutes_from_iso_pair_supports_overnight_shift(): void
    {
        $this->assertSame(
            8 * 60,
            FingerprintHoursHelper::minutesFromIsoPair('2026-07-01T22:00:00', '2026-07-01T06:00:00')
        );
    }

    public function test_resolve_work_day_minutes_is_zero_for_single_punch(): void
    {
        $minutes = FingerprintHoursHelper::resolveWorkDayMinutes([
            'date' => '2026-07-01',
            'check_in' => '10:13 AM',
            'check_out' => '10:13 AM',
            'time_in' => '2026-07-01T10:13:00',
            'time_out' => '2026-07-01T10:13:00',
            'hours' => '00:00',
        ], 8);

        $this->assertSame(0, $minutes);
    }

    public function test_month_hours_padding_never_invents_hours_for_sparse_sheets(): void
    {
        $this->assertSame(0, FingerprintHoursHelper::monthHoursPaddingMinutes(23, 5, 8));
        $this->assertSame(0, FingerprintHoursHelper::monthHoursPaddingMinutes(31, 5, 8));
        $this->assertSame(8 * 60, FingerprintHoursHelper::monthHoursPaddingMinutes(31, 4, 8));
    }

    public function test_extra_day_merit_amount_uses_30_day_formula(): void
    {
        $this->assertSame(1100.0, FingerprintHoursHelper::extraDayMeritAmount(22000, 8));
        $this->assertSame(300.0, FingerprintHoursHelper::extraDayMeritAmount(6000, 9));
    }

    public function test_friday_attendance_merit_exact_full_day_is_normal_day_only(): void
    {
        $amount = FingerprintHoursHelper::fridayAttendanceMeritAmount(22000, 8, 8);
        $this->assertEqualsWithDelta(
            FingerprintHoursHelper::fridayNormalDayAmount(22000),
            $amount,
            0.01
        );
        $this->assertEqualsWithDelta(22000 / 30, $amount, 0.01);
        $this->assertTrue(FingerprintHoursHelper::isFridayFullDay(8, 8));
    }

    public function test_friday_attendance_merit_overtime_adds_normal_day_plus_extra_hours(): void
    {
        // يوم عادي (راتب÷30) + ساعتان إضافي × (22000/208) × 2
        $amount = FingerprintHoursHelper::fridayAttendanceMeritAmount(22000, 8, 10);
        $this->assertEqualsWithDelta(
            FingerprintHoursHelper::fridayNormalDayAmount(22000) + 2 * (22000 / 208) * 2,
            $amount,
            0.01
        );
    }

    public function test_friday_attendance_merit_partial_is_extra_hours_only(): void
    {
        // 5 × (22000/208) × 2
        $amount = FingerprintHoursHelper::fridayAttendanceMeritAmount(22000, 8, 5);
        $this->assertEqualsWithDelta(5 * (22000 / 208) * 2, $amount, 0.01);
        $this->assertFalse(FingerprintHoursHelper::isFridayFullDay(5, 8));
    }

    public function test_friday_attendance_accountant_example_20_3_hours(): void
    {
        // 20:18 = 20.3س → يوم عادي 350 + 12.3س × (10500/208)×2
        $amount = FingerprintHoursHelper::fridayAttendanceMeritAmount(10500, 8, 20.3);
        $this->assertEqualsWithDelta(10500 / 30 + 12.3 * (10500 / 208) * 2, $amount, 0.01);
        $this->assertEqualsWithDelta(1591.83, $amount, 0.01);
    }

    public function test_is_friday_date(): void
    {
        $this->assertTrue(FingerprintHoursHelper::isFridayDate('2026-07-03'));
        $this->assertFalse(FingerprintHoursHelper::isFridayDate('2026-07-04'));
    }

    public function test_has_friday_attendance(): void
    {
        $this->assertTrue(FingerprintHoursHelper::hasFridayAttendance([
            'check_in' => '08:00 AM',
            'check_out' => '04:00 PM',
            'hours' => '08:00',
        ]));
        $this->assertFalse(FingerprintHoursHelper::hasFridayAttendance([
            'check_in' => '08:00 AM',
            'check_out' => '08:00 AM',
            'hours' => '00:00',
        ]));
    }

    public function test_absence_day_amount_uses_salary_over_30(): void
    {
        $this->assertSame(500.0, FingerprintHoursHelper::absenceDayAmount(15000, 1));
        $this->assertSame(1000.0, FingerprintHoursHelper::absenceDayAmount(15000, 2));
        $this->assertSame(1.0, FingerprintHoursHelper::absenceDayCount(null));
        $this->assertSame(2.0, FingerprintHoursHelper::absenceDayCount(2));
        $this->assertTrue(FingerprintHoursHelper::isAbsentDay([
            'hours' => '00:00',
            'check_in' => '08:00 AM',
        ]));
        $this->assertFalse(FingerprintHoursHelper::isAbsentDay([
            'hours' => '00:00',
            'check_in' => '08:00 AM',
        ], null, true));
    }

    public function test_absent_day_placeholder_restores_payroll_absence_not_worked_shift(): void
    {
        $permissionDay = [
            'date' => '2026-08-26',
            'hours' => '08:00',
            'check_in' => '08:00 AM',
            'check_out' => '04:00 PM',
            'hours_permission' => '08:00',
        ];

        $this->assertTrue(FingerprintHoursHelper::isFullDayPermission($permissionDay['hours_permission'], 8));
        $this->assertFalse(FingerprintHoursHelper::isAbsentDay($permissionDay));

        $clearedPermissionOnly = $permissionDay;
        $clearedPermissionOnly['hours_permission'] = null;
        $this->assertFalse(FingerprintHoursHelper::isAbsentDay($clearedPermissionOnly));
        $this->assertSame(8 * 60, FingerprintHoursHelper::resolveWorkDayMinutes($clearedPermissionOnly, 8));

        $reverted = array_merge($permissionDay, FingerprintHoursHelper::absentDayPlaceholderPayload('2026-08-26'));
        $this->assertNull($reverted['hours_permission']);
        $this->assertSame('00:00', $reverted['hours']);
        $this->assertSame('08:00 AM', $reverted['check_in']);
        $this->assertSame('08:00 AM', $reverted['check_out']);
        $this->assertFalse(FingerprintHoursHelper::isFullDayPermission($reverted['hours_permission'], 8));
        $this->assertTrue(FingerprintHoursHelper::isAbsentDay($reverted));
        $this->assertSame(0, FingerprintHoursHelper::resolveWorkDayMinutes($reverted, 8));
        $this->assertSame(350.0, FingerprintHoursHelper::absenceDayAmount(10500, 1));
    }

    public function test_friday_extra_day_reason(): void
    {
        $this->assertSame(
            'إضافي يوم كامل (2026-07-03)',
            FingerprintHoursHelper::fridayExtraDayReason('2026-07-03')
        );
        $this->assertSame(
            'إضافي جمعة (2026-07-03)',
            FingerprintHoursHelper::fridayPartialReason('2026-07-03')
        );
        $this->assertSame(
            'مكافأة حضور الجمعة (2026-07-03)',
            FingerprintHoursHelper::fridayBonusReason('2026-07-03')
        );
        $this->assertSame(
            'مكافأة حضور الجمعة (2026-07-03): ملاحظة',
            FingerprintHoursHelper::fridayBonusReason('2026-07-03', 'ملاحظة')
        );
    }

    public function test_period_for_month_runs_from_previous_26_to_close_day_25(): void
    {
        $period = FingerprintHoursHelper::periodForMonth(2026, 8);

        $this->assertSame(2026, $period['year']);
        $this->assertSame(8, $period['month']);
        $this->assertSame('2026-07-26', $period['date_from']);
        $this->assertSame('2026-08-25', $period['date_to']);
    }

    public function test_period_for_january_crosses_year_boundary(): void
    {
        $period = FingerprintHoursHelper::periodForMonth(2026, 1);

        $this->assertSame('2025-12-26', $period['date_from']);
        $this->assertSame('2026-01-25', $period['date_to']);
    }

    public function test_payroll_month_for_date_starts_next_month_after_close_day(): void
    {
        $this->assertSame(['year' => 2026, 'month' => 8], FingerprintHoursHelper::payrollMonthForDate('2026-08-25'));
        $this->assertSame(['year' => 2026, 'month' => 9], FingerprintHoursHelper::payrollMonthForDate('2026-08-26'));
        $this->assertSame(['year' => 2026, 'month' => 1], FingerprintHoursHelper::payrollMonthForDate('2025-12-26'));
    }

    public function test_friday_dates_in_month_use_close_day_period(): void
    {
        $fridays = FingerprintHoursHelper::fridayDatesInMonth(2026, 6);

        $this->assertContains('2026-05-29', $fridays);
        $this->assertNotContains('2026-06-26', $fridays);
        foreach ($fridays as $date) {
            $this->assertGreaterThanOrEqual('2026-05-26', $date);
            $this->assertLessThanOrEqual('2026-06-25', $date);
            $this->assertSame(5, (int) date('w', strtotime($date)));
        }
    }

    public function test_score_attendance_day_keeps_overtime_and_late_separate(): void
    {
        $fixed = 10500.0;
        $hourPrice = FingerprintHoursHelper::baseHourPrice($fixed, 8);

        $overtime = FingerprintHoursHelper::scoreAttendanceDay([
            'date' => '2026-08-03',
            'check_in' => '08:00 AM',
            'check_out' => '06:00 PM',
            'hours' => '10:00',
        ], $fixed, 8);
        $this->assertSame('overtime', $overtime['kind']);
        $this->assertEqualsWithDelta(2 * $hourPrice * 1.5, $overtime['amount'], 0.01);

        $late = FingerprintHoursHelper::scoreAttendanceDay([
            'date' => '2026-08-04',
            'check_in' => '08:00 AM',
            'check_out' => '02:00 PM',
            'hours' => '06:00',
        ], $fixed, 8);
        $this->assertSame('late', $late['kind']);
        $this->assertEqualsWithDelta(2 * $hourPrice * 1.5, $late['amount'], 0.01);

        $absent = FingerprintHoursHelper::scoreAttendanceDay([
            'date' => '2026-08-05',
            'check_in' => '08:00 AM',
            'check_out' => '08:00 AM',
            'hours' => '00:00',
        ], $fixed, 8);
        $this->assertSame('absent', $absent['kind']);
        $this->assertEqualsWithDelta($fixed / 30, $absent['amount'], 0.01);
    }
}
