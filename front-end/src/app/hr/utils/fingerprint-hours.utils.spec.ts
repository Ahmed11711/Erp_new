import {
  applyNormalShiftTimes,
  baseHourPrice,
  defaultShiftCheckOut,
  deductionMultiplier,
  fullDayPermissionSavePayload,
  absentDayPlaceholderSavePayload,
  hoursFromTimeInOut,
  isAbsentDay,
  isFullDayPermissionLeave,
  isFridayDate,
  hasFridayAttendance,
  absenceDayAmount,
  absenceDayCount,
  isAbsenceDoubled,
  isFullDayPermission,
  monthHoursPaddingMinutes,
  normalizeOvernightFingerPrintRecords,
  overtimeDeductionRate,
  extraDayMeritAmountAction,
  extraDayMeritAmount,
  attendanceDayBonusAmount,
  attendanceDayBonusLabel,
  attendanceDayBonusReason,
  attendanceDayBonusType,
  parseAttendanceDayBonusReason,
  summarizeAttendanceHalfDayBonuses,
  fridayAttendanceMeritAmount,
  fridayAttendanceLabel,
  fridayNormalDayAmount,
  isFridayFullDay,
  overtimeMeritRowAmount,
  overtimeMeritReasonLabel,
  overtimeMeritRowReason,
  parseOvertimeMeritReason,
  deductionRowReason,
  parseDeductionReason,
  summarizeDeductionCounts,
  summarizeOvertimeMeritCounts,
  overtimeMeritCountsLabel,
  hoursDifferenceToMinutes,
  parseYearMonthFromDate,
  parseDatetimeLocalValue,
  EXTRA_DAY_WORKING_DAYS,
  OVERTIME_DEDUCTION_MULTIPLIER,
  resolveWorkDayHours,
  fingerprintDayFromPunches,
  sumFingerprintWorkedMinutes,
  WORKING_DAYS_PER_MONTH,
  defaultFingerprintMonthValue,
  eachDateInRange,
  fingerprintPeriodForMonth,
  fridayDatesInRange,
  payrollMonthForDate,
  annotateAttendanceDay,
  buildAttendanceMonthAccount,
  buildPayrollRowFromAttendance,
} from './fingerprint-hours.utils';

describe('fingerprint-hours.utils — Ziad hour rates', () => {
  it('baseHourPrice uses 26 working days (208 / 234)', () => {
    expect(WORKING_DAYS_PER_MONTH).toBe(26);
    expect(baseHourPrice(6000, 8)).toBeCloseTo(6000 / 208, 5);
    expect(baseHourPrice(6000, 9)).toBeCloseTo(6000 / 234, 5);
  });

  it('overtimeDeductionRate applies 1.5 multiplier', () => {
    expect(overtimeDeductionRate(6000, 8)).toBeCloseTo((6000 / 208) * OVERTIME_DEDUCTION_MULTIPLIER, 5);
    expect(overtimeDeductionRate(6000, 9)).toBeCloseTo((6000 / 234) * OVERTIME_DEDUCTION_MULTIPLIER, 5);
  });

  it('deductionMultiplier defaults to 1.5 or uses absence_deduction', () => {
    expect(deductionMultiplier()).toBe(1.5);
    expect(deductionMultiplier(null)).toBe(1.5);
    expect(deductionMultiplier(2)).toBe(2);
  });
 
  it('extraDayMeritAmount uses 26-day overtime rate × day hours', () => {
    expect(extraDayMeritAmount(6000, 8)).toBeCloseTo((6000 / 208) * 1.5 * 8, 5);
  });

  it('attendance day bonus uses extra-day formula for a full or half day', () => {
    expect(attendanceDayBonusReason('2026-08-26', 1)).toBe('إضافي يوم كامل (2026-08-26)');
    expect(attendanceDayBonusReason('2026-08-26', 0.5)).toBe('مكافأة نصف يوم (2026-08-26)');
    expect(attendanceDayBonusType(1)).toBe('حوافز');
    expect(attendanceDayBonusType(0.5)).toBe('مكافئات');
    expect(attendanceDayBonusLabel(1)).toBe('مضاعفة اليوم');
    expect(attendanceDayBonusLabel(0.5)).toBe('مكافأة نصف يوم');
    expect(attendanceDayBonusAmount(15000, 8, 1)).toBeCloseTo((15000 / 30) * 1.5, 5);
    expect(attendanceDayBonusAmount(15000, 8, 0.5)).toBeCloseTo((15000 / 30) * 1.5 * 0.5, 5);
    expect(parseAttendanceDayBonusReason('إضافي يوم كامل (2026-08-26)')).toEqual({ date: '2026-08-26', days: 1 });
    expect(parseAttendanceDayBonusReason('مكافأة نصف يوم (2026-08-26)')).toEqual({ date: '2026-08-26', days: 0.5 });
    expect(summarizeAttendanceHalfDayBonuses([
      { reason: 'مكافأة نصف يوم (2026-08-26)', amount: 375 },
      { reason: 'مكافأة نصف يوم (2026-08-27)', amount: 375 },
      { reason: 'إضافي يوم كامل (2026-08-28)', amount: 750 },
    ])).toEqual({ count: 2, amount: 750 });
  });

  it('extraDayMeritAmountAction uses 30 working days for add-extra-day button only', () => {
    expect(extraDayMeritAmountAction(22000, 8)).toBeCloseTo((22000 / 30) * 1.5, 5);
    expect(extraDayMeritAmountAction(6000, 9)).toBeCloseTo((6000 / 30) * 1.5, 5);
  });

  it('overtimeMeritRowAmount supports full day and hours rows', () => {
    expect(overtimeMeritRowAmount(22000, 8, { date: '2026-07-01', kind: 'day' }))
      .toBeCloseTo(extraDayMeritAmountAction(22000, 8), 5);
    expect(overtimeMeritRowAmount(22000, 8, { date: '2026-07-01', kind: 'hours', hours: 2 }))
      .toBeCloseTo(overtimeDeductionRate(22000, 8) * 2, 5);
    expect(overtimeMeritRowAmount(22000, 8, { date: '2026-07-01', kind: 'amount', amount: 150 }))
      .toBe(150);
  });

  it('overtimeMeritRowReason and parseYearMonthFromDate', () => {
    expect(overtimeMeritRowReason({ date: '2026-07-15', kind: 'day' })).toBe('إضافي يوم كامل (2026-07-15)');
    expect(overtimeMeritRowReason({ date: '2026-07-15', kind: 'hours', hours: 3 })).toBe('إضافي 3 ساعة (2026-07-15)');
    expect(parseYearMonthFromDate('2026-07-15')).toEqual({ year: 2026, month: 7 });
    expect(parseYearMonthFromDate('bad')).toBeNull();
  });

  it('deductionRowReason and parseDeductionReason match overtime style', () => {
    expect(deductionRowReason({ date: '2026-07-15', kind: 'day' })).toBe('خصم يوم كامل (2026-07-15)');
    expect(deductionRowReason({ date: '2026-07-15', kind: 'hours', hours: 3 })).toBe('خصم 3 ساعة (2026-07-15)');
    expect(parseDeductionReason('خصم يوم كامل (2026-07-01)')).toEqual({
      date: '2026-07-01',
      kind: 'day',
      hours: null,
    });
    expect(parseDeductionReason('خصم 2.5 ساعة (2026-07-03)')).toEqual({
      date: '2026-07-03',
      kind: 'hours',
      hours: 2.5,
    });
    expect(deductionRowReason({ date: '2026-07-15', kind: 'amount', amount: 150 })).toBe('خصم مبلغ (2026-07-15)');
    expect(parseDeductionReason('خصم مبلغ (2026-07-15)')).toBeNull();
  });

  it('summarizeDeductionCounts counts days and hours from خصومات reasons', () => {
    const counts = summarizeDeductionCounts([
      { type: 'خصومات', reason: 'خصم يوم كامل (2026-07-01)', amount: 350 },
      { type: 'خصومات', reason: 'خصم 2 ساعة (2026-07-04)', amount: 200 },
      { type: 'غياب', reason: 'خصم يوم كامل (2026-07-05)', amount: 1 },
      { type: 'خصومات', reason: 'خصم يدوي', amount: 100 },
    ]);
    expect(counts).toEqual({
      deductionDays: 1,
      deductionHours: 2,
      deductionDaysAmount: 350,
      deductionHoursAmount: 200,
    });
  });

  it('parseOvertimeMeritReason reads saved incentive reasons', () => {
    expect(parseOvertimeMeritReason('إضافي يوم كامل (2026-07-01)')).toEqual({
      date: '2026-07-01',
      kind: 'day',
      hours: null,
    });
    expect(parseOvertimeMeritReason('إضافي 2.5 ساعة (2026-07-03)')).toEqual({
      date: '2026-07-03',
      kind: 'hours',
      hours: 2.5,
    });
    expect(parseOvertimeMeritReason('مكافأة')).toBeNull();
    expect(overtimeMeritReasonLabel({ date: '2026-07-01', kind: 'day', hours: null })).toBe('يوم كامل');
  });

  it('summarizeOvertimeMeritCounts counts extra days and hours from incentive reasons', () => {
    const counts = summarizeOvertimeMeritCounts([
      { type: 'حوافز', reason: 'إضافي يوم كامل (2026-07-01)', amount: 350 },
      { type: 'حوافز', reason: 'إضافي يوم كامل (2026-07-03)', amount: 350 },
      { type: 'حوافز', reason: 'إضافي 2.5 ساعة (2026-07-04)', amount: 250 },
      { type: 'مكافئات', reason: 'إضافي يوم كامل (2026-07-05)', amount: 350 },
      { type: 'حوافز', reason: 'مكافأة', amount: 100 },
    ]);
    expect(counts).toEqual({ extraDays: 2, extraHours: 2.5, extraDaysAmount: 700, extraHoursAmount: 250 });
    expect(overtimeMeritCountsLabel(counts)).toBe('2 يوم إضافي + 2.5 ساعة');
    expect(overtimeMeritCountsLabel({ extraDays: 1, extraHours: 0, extraDaysAmount: 350, extraHoursAmount: 0 })).toBe('1 يوم إضافي');
    expect(overtimeMeritCountsLabel({ extraDays: 0, extraHours: 3, extraDaysAmount: 0, extraHoursAmount: 100 })).toBe('3 ساعة');
    expect(overtimeMeritCountsLabel({ extraDays: 0, extraHours: 0, extraDaysAmount: 0, extraHoursAmount: 0 })).toBe('');
  });

  it('hoursDifferenceToMinutes reads overtime and late labels', () => {
    expect(hoursDifferenceToMinutes('02:30')).toBe(150);
    expect(hoursDifferenceToMinutes('-01:15')).toBe(-75);
    expect(hoursDifferenceToMinutes('1 يوم')).toBe(0);
    expect(hoursDifferenceToMinutes('')).toBe(0);
  });
});

describe('fingerprint-hours.utils — single punch & month padding', () => {
  it('hoursFromTimeInOut returns null when time_in equals time_out (not 24h)', () => {
    expect(hoursFromTimeInOut('2026-07-01T10:13:00', '2026-07-01T10:13:00')).toBeNull();
  });

  it('hoursFromTimeInOut still supports real overnight shifts', () => {
    expect(hoursFromTimeInOut('2026-07-01T22:00:00', '2026-07-01T06:00:00')).toBe('08:00');
  });

  it('resolveWorkDayHours is 00:00 for a single punch day', () => {
    const hours = resolveWorkDayHours({
      date: '2026-07-01',
      check_in: '10:13 AM',
      check_out: '10:13 AM',
      time_in: '2026-07-01T10:13:00',
      time_out: '2026-07-01T10:13:00',
      times: ['2026-07-01T10:13:00'],
      hours: '00:00',
      working_hours: 8,
    });
    expect(hours).toBe('00:00');
  });

  it('resolveWorkDayHours uses first and last punch, not locale 12h display', () => {
    expect(resolveWorkDayHours({
      date: '2026-08-03',
      check_in: '09:00 ص',
      check_out: '05:00 م',
      time_in: '2026-08-03T09:07:00',
      time_out: '2026-08-03T17:12:00',
      times: ['2026-08-03T09:07:00', '2026-08-03T12:01:00', '2026-08-03T17:12:00'],
      hours: '08:00',
      working_hours: 8,
    })).toBe('08:05');
  });

  it('fingerprintDayFromPunches matches machine first-to-last span', () => {
    const day = fingerprintDayFromPunches([
      '2026-08-03T17:12:00',
      '2026-08-03T09:07:00',
      '2026-08-03T12:01:00',
    ]);
    expect(day?.hours).toBe('08:05');
    expect(day?.check_in).toBe('09:07 AM');
    expect(day?.check_out).toBe('05:12 PM');
    expect(day?.times).toEqual([
      '2026-08-03T09:07:00',
      '2026-08-03T12:01:00',
      '2026-08-03T17:12:00',
    ]);
  });

  it('sumFingerprintWorkedMinutes totals punch hours only', () => {
    const minutes = sumFingerprintWorkedMinutes([
      {
        date: '2026-08-03',
        time_in: '2026-08-03T09:00:00',
        time_out: '2026-08-03T17:00:00',
        times: ['2026-08-03T09:00:00', '2026-08-03T17:00:00'],
        hours: '08:00',
      },
      {
        date: '2026-08-04',
        time_in: '2026-08-04T09:00:00',
        time_out: '2026-08-04T13:30:00',
        times: ['2026-08-04T09:00:00', '2026-08-04T13:30:00'],
        hours: '04:30',
      },
      {
        date: '2026-08-05',
        hours: '08:00',
        hours_permission: '08:00',
        check_in: '08:00 AM',
        check_out: '04:00 PM',
      },
    ], 8);
    expect(minutes).toBe(8 * 60 + 4 * 60 + 30);
  });

  it('monthHoursPaddingMinutes never invents hours for sparse sheets', () => {
    // 23 fingerprint days, 5 Fridays → previously added 64h; must stay 0
    expect(monthHoursPaddingMinutes(23, 5, 8)).toBe(0);
    // full July 31 days / 5 Fridays → no excess
    expect(monthHoursPaddingMinutes(31, 5, 8)).toBe(0);
    // 27 workdays equivalent → subtract one day
    expect(monthHoursPaddingMinutes(31, 4, 8)).toBe(8 * 60);
  });
});

describe('fingerprint-hours.utils — Friday attendance', () => {
  it('detects Friday dates', () => {
    expect(isFridayDate('2026-07-03')).toBeTrue(); // Friday
    expect(isFridayDate('2026-07-04')).toBeFalse(); // Saturday
  });

  it('detects Friday attendance vs empty holiday', () => {
    expect(hasFridayAttendance({
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      hours: '08:00',
    })).toBeTrue();
    expect(hasFridayAttendance({
      check_in: '08:00 AM',
      check_out: '08:00 AM',
      hours: '00:00',
    })).toBeFalse();
  });

  it('exact full Friday = normal day only (salary ÷ 30)', () => {
    const amount = fridayAttendanceMeritAmount(22000, 8, 8);
    expect(amount).toBeCloseTo(fridayNormalDayAmount(22000), 5);
    expect(amount).toBeCloseTo(22000 / 30, 5);
    expect(isFridayFullDay(8, 8)).toBeTrue();
    expect(fridayAttendanceLabel(8, 8)).toBe('جمعة (يوم عادي)');
  });

  it('Friday over basic hours = normal day + extra overtime hours', () => {
    const amount = fridayAttendanceMeritAmount(22000, 8, 10);
    expect(amount).toBeCloseTo(
      fridayNormalDayAmount(22000) + 2 * (22000 / 208) * 2,
      5
    );
    expect(fridayAttendanceLabel(10, 8)).toBe('جمعة (يوم عادي + ساعات إضافي)');
  });

  it('partial Friday = extra hours only (double normal hour)', () => {
    expect(fridayAttendanceMeritAmount(22000, 8, 5)).toBeCloseTo(5 * (22000 / 208) * 2, 5);
    expect(isFridayFullDay(5, 8)).toBeFalse();
    expect(fridayAttendanceLabel(5, 8)).toBe('جمعة (ساعات إضافي)');
  });

  it('accountant Friday example: 20.3h on 10500 salary', () => {
    // 20:18 = 20.3س → يوم عادي 350 + 12.3س × (10500/208)×2
    const amount = fridayAttendanceMeritAmount(10500, 8, 20.3);
    expect(amount).toBeCloseTo(10500 / 30 + 12.3 * (10500 / 208) * 2, 5);
    expect(amount).toBeCloseTo(1591.83, 2);
  });
});

describe('fingerprint-hours.utils — absent day', () => {
  it('detects absent placeholder days', () => {
    expect(isAbsentDay({
      hours: '00:00',
      check_in: '08:00 AM',
      holiday: false,
      vacation: false,
      hours_permission: null,
    })).toBeTrue();
  });

  it('rejects holidays, vacations, permission and worked days', () => {
    expect(isAbsentDay({ hours: '00:00', check_in: '08:00 AM', holiday: true })).toBeFalse();
    expect(isAbsentDay({ hours: '00:00', check_in: '08:00 AM', vacation: true })).toBeFalse();
    expect(isAbsentDay({ hours: '00:00', check_in: '08:00 AM', hours_permission: '08:00' })).toBeFalse();
    expect(isAbsentDay({ hours: '08:00', check_in: '08:00 AM' })).toBeFalse();
    expect(isAbsentDay({ hours: '00:00', check_in: '09:00 AM' })).toBeFalse();
  });

  it('absenceDayAmount uses salary / 30', () => {
    expect(absenceDayAmount(15000, 1)).toBeCloseTo(500, 5);
    expect(absenceDayAmount(15000, 2)).toBeCloseTo(1000, 5);
    expect(absenceDayCount(null)).toBe(1);
    expect(absenceDayCount(2)).toBe(2);
    expect(isAbsenceDoubled(null)).toBeFalse();
    expect(isAbsenceDoubled(1)).toBeFalse();
    expect(isAbsenceDoubled(2)).toBeTrue();
    expect(isAbsenceDoubled('2')).toBeTrue();
  });
});

describe('fingerprint-hours.utils — full day permission', () => {
  it('detects full day permission for 08:00 on 8-hour shift', () => {
    expect(isFullDayPermission('08:00', 8)).toBeTrue();
    expect(isFullDayPermission('07:59', 8)).toBeFalse();
    expect(isFullDayPermission('09:00', 9)).toBeTrue();
  });

  it('sets normal shift checkout times', () => {
    expect(defaultShiftCheckOut(8)).toBe('04:00 PM');
    expect(defaultShiftCheckOut(9)).toBe('05:00 PM');
  });

  it('applyNormalShiftTimes uses 08:00 AM to shift end', () => {
    const row = applyNormalShiftTimes({ check_in: '', check_out: '', hours: '' }, 8);
    expect(row.check_in).toBe('08:00 AM');
    expect(row.check_out).toBe('04:00 PM');
    expect(row.hours).toBe('08:00');
  });

  it('fullDayPermissionSavePayload returns shift fields only for full day', () => {
    expect(fullDayPermissionSavePayload('08:00', 8)).toEqual({
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      hours: '08:00',
    });
    expect(fullDayPermissionSavePayload('04:00', 8)).toEqual({});
  });

  it('detects full day permission leave and not holidays', () => {
    expect(isFullDayPermissionLeave({ hours_permission: '08:00' }, 8)).toBeTrue();
    expect(isFullDayPermissionLeave({ hours_permission: '04:00' }, 8)).toBeFalse();
    expect(isFullDayPermissionLeave({ hours_permission: '08:00', holiday: true }, 8)).toBeFalse();
    expect(isFullDayPermissionLeave({ hours_permission: '08:00', vacation: true }, 8)).toBeFalse();
  });

  it('absentDayPlaceholderSavePayload restores absence so payroll deducts a day', () => {
    const permissionDay = {
      hours: '08:00',
      check_in: '08:00 AM',
      check_out: '04:00 PM',
      hours_permission: '08:00',
    };
    expect(isAbsentDay(permissionDay)).toBeFalse();

    const clearedPermissionOnly = { ...permissionDay, hours_permission: null };
    expect(isAbsentDay(clearedPermissionOnly)).toBeFalse();

    const reverted = { ...permissionDay, ...absentDayPlaceholderSavePayload() };
    expect(reverted.hours_permission).toBeNull();
    expect(reverted.check_in).toBe('08:00 AM');
    expect(reverted.check_out).toBe('08:00 AM');
    expect(reverted.hours).toBe('00:00');
    expect(isAbsentDay(reverted)).toBeTrue();
    expect(isFullDayPermissionLeave(reverted, 8)).toBeFalse();
  });

  it('resolveWorkDayHours ignores stale 24h times when full day permission', () => {
    const hours = resolveWorkDayHours({
      date: '2026-05-26',
      check_in: '08:00 AM',
      check_out: '08:00 AM',
      hours_permission: '08:00',
      working_hours: 8,
      times: ['2026-05-26T08:00:00', '2026-05-27T08:00:00'],
      hours: '24:00',
    });
    expect(hours).toBe('08:00');
  });

  it('normalizeOvernightFingerPrintRecords skips merge for permission days', () => {
    const records = normalizeOvernightFingerPrintRecords([
      {
        date: '2026-05-26',
        hours_permission: '08:00',
        working_hours: 8,
        check_in: '08:00 AM',
        check_out: '08:00 AM',
        hours: '00:00',
        times: [],
      },
      {
        date: '2026-05-27',
        check_in: '08:00 AM',
        check_out: '08:00 AM',
        hours: '00:00',
        times: ['2026-05-27T07:30:00'],
      },
    ]);

    const permDay = records.find(r => r.date === '2026-05-26');
    expect(permDay?.hours).toBe('08:00');
    expect(permDay?.check_in).toBe('08:00 AM');
    expect(permDay?.check_out).toBe('04:00 PM');
    expect(records.some(r => r.date === '2026-05-27')).toBeTrue();
  });

  it('parseDatetimeLocalValue keeps 23:59 on the same calendar day', () => {
    const parsed = parseDatetimeLocalValue('2026-07-01T23:59');
    expect(parsed).toBeTruthy();
    expect(parsed?.getFullYear()).toBe(2026);
    expect(parsed?.getMonth()).toBe(6);
    expect(parsed?.getDate()).toBe(1);
    expect(parsed?.getHours()).toBe(23);
    expect(parsed?.getMinutes()).toBe(59);
  });

  it('overnight merge keeps a same-day 23:59 checkout and does not steal 00:01', () => {
    const records = normalizeOvernightFingerPrintRecords([
      {
        date: '2026-07-01',
        check_in: '10:59 AM',
        check_out: '11:59 PM',
        hours: '13:00',
        times: ['2026-07-01T10:59:00', '2026-07-01T23:59:00'],
        time_in: '2026-07-01T10:59:00',
        time_out: '2026-07-01T23:59:00',
      },
      {
        date: '2026-07-02',
        check_in: '12:01 AM',
        check_out: '12:01 AM',
        hours: '00:00',
        times: ['2026-07-02T00:01:00'],
        time_in: '2026-07-02T00:01:00',
        time_out: '2026-07-02T00:01:00',
      },
    ]);

    const july1 = records.find(r => r.date === '2026-07-01');
    expect(july1?.check_out).toBe('11:59 PM');
    expect(july1?.time_out).toBe('2026-07-01T23:59:00');
  });

  it('overnight merge still attaches 00:01 when the previous day has check-in only', () => {
    const records = normalizeOvernightFingerPrintRecords([
      {
        date: '2026-07-01',
        check_in: '10:59 AM',
        check_out: '10:59 AM',
        hours: '00:00',
        times: ['2026-07-01T10:59:00'],
        time_in: '2026-07-01T10:59:00',
        time_out: '2026-07-01T10:59:00',
      },
      {
        date: '2026-07-02',
        check_in: '12:01 AM',
        check_out: '12:01 AM',
        hours: '00:00',
        times: ['2026-07-02T00:01:00'],
        time_in: '2026-07-02T00:01:00',
        time_out: '2026-07-02T00:01:00',
      },
    ]);

    const july1 = records.find(r => r.date === '2026-07-01');
    expect(july1?.check_out).toBe('12:01 AM');
    expect(july1?.time_out).toBe('2026-07-02T00:01:00');
  });
});

describe('fingerprint-hours.utils — close day 25 period', () => {
  it('fingerprintPeriodForMonth is previous 26 through close day 25', () => {
    expect(fingerprintPeriodForMonth(2026, 8)).toEqual({
      year: 2026,
      month: 8,
      dateFrom: '2026-07-26',
      dateTo: '2026-08-25',
    });
  });

  it('january period crosses the year boundary', () => {
    expect(fingerprintPeriodForMonth(2026, 1)).toEqual({
      year: 2026,
      month: 1,
      dateFrom: '2025-12-26',
      dateTo: '2026-01-25',
    });
  });

  it('payrollMonthForDate starts the next month after the 25th', () => {
    expect(payrollMonthForDate('2026-08-25')).toEqual({ year: 2026, month: 8 });
    expect(payrollMonthForDate('2026-08-26')).toEqual({ year: 2026, month: 9 });
    expect(payrollMonthForDate('2025-12-26')).toEqual({ year: 2026, month: 1 });
  });

  it('defaultFingerprintMonthValue follows the period containing today', () => {
    expect(defaultFingerprintMonthValue(new Date(2026, 7, 17))).toBe('2026-08');
    expect(defaultFingerprintMonthValue(new Date(2026, 7, 26))).toBe('2026-09');
  });

  it('eachDateInRange and fridayDatesInRange cover the close-day window', () => {
    const dates = eachDateInRange('2026-07-26', '2026-08-25');
    expect(dates[0]).toBe('2026-07-26');
    expect(dates[dates.length - 1]).toBe('2026-08-25');
    expect(dates).toContain('2026-07-31');
    expect(fridayDatesInRange('2026-07-26', '2026-08-25')).toContain('2026-07-31');
  });
});

describe('fingerprint-hours.utils — payroll matches attendance sheet', () => {
  const hourPrice = baseHourPrice(10500, 8);

  it('keeps overtime and late hours separate instead of netting them', () => {
    const overtimeDay: any = {
      date: '2026-08-03',
      check_in: '08:00 AM',
      check_out: '06:00 PM',
      hours: '10:00',
      holiday: false,
      vacation: false,
    };
    annotateAttendanceDay(overtimeDay, { dayHours: 8, fixedSalary: 10500, hourPrice });
    expect(overtimeDay.salary_type2).toBe('حافز');
    expect(overtimeDay.hoursDifference).toBe('02:00');
    expect(overtimeDay.salary_type).toBeCloseTo(2 * hourPrice * 1.5, 2);

    const lateDay: any = {
      date: '2026-08-04',
      check_in: '08:00 AM',
      check_out: '02:00 PM',
      hours: '06:00',
      holiday: false,
      vacation: false,
    };
    annotateAttendanceDay(lateDay, { dayHours: 8, fixedSalary: 10500, hourPrice });
    expect(lateDay.salary_type2).toBe('خصم');
    expect(lateDay.hoursDifference).toBe('-02:00');
    expect(lateDay.salary_type).toBeCloseTo(2 * hourPrice * 1.5, 2);

    const absentDay: any = {
      date: '2026-08-05',
      check_in: '08:00 AM',
      check_out: '08:00 AM',
      hours: '00:00',
      holiday: false,
      vacation: false,
    };
    annotateAttendanceDay(absentDay, { dayHours: 8, fixedSalary: 10500, hourPrice });
    expect(absentDay.absence_day).toBe(true);
    expect(absentDay.salary_type).toBeCloseTo(10500 / 30, 2);

    const account = buildAttendanceMonthAccount({
      tableData: [overtimeDay, lateDay, absentDay],
      merits: [],
      subtractions: [],
      advancePayments: [],
      fixedSalary: 10500,
    });
    expect(account.overtimeFromHours).toBeCloseTo(2 * hourPrice * 1.5, 2);
    expect(account.hoursDeduction).toBeCloseTo(2 * hourPrice * 1.5, 2);
    expect(account.absenceSub).toBeCloseTo(10500 / 30, 2);
    expect(account.totalMerit).toBeCloseTo(10500 + account.overtimeFromHours, 2);
    expect(account.totalSub).toBeCloseTo(account.hoursDeduction + account.absenceSub, 2);
    expect(account.netTotal).toBeCloseTo(account.totalMerit - account.totalSub, 2);
  });

  it('payroll row follows attendance overtime, late hours and absence days', () => {
    const row = buildPayrollRowFromAttendance({
      id: 18,
      fixed_salary: 10500,
      working_hours: 8,
      finger_print: [
        {
          id: 1,
          date: '2026-08-03',
          check_in: '08:00 AM',
          check_out: '06:00 PM',
          hours: '10:00',
          reviewed: false,
        },
        {
          id: 2,
          date: '2026-08-04',
          check_in: '08:00 AM',
          check_out: '02:00 PM',
          hours: '06:00',
          reviewed: false,
        },
        {
          id: 3,
          date: '2026-08-05',
          check_in: '08:00 AM',
          check_out: '08:00 AM',
          hours: '00:00',
          reviewed: false,
        },
      ],
      merits: [{ type: 'حوافز', reason: 'إضافي يوم كامل (2026-08-07)', amount: 350 }],
      subtraction: [],
      advance_payment: [],
    }, {
      dateFrom: '2026-08-03',
      dateTo: '2026-08-05',
      holidayDays: [],
    });

    expect(row.extraHours).toBeCloseTo(2 * hourPrice * 1.5, 2);
    expect(row.absenceDetails.absenceHours).toBe('02:00');
    expect(row.absence).toBe(1);
    expect(row.incentives).toBe(350);
    expect(row.isReviewed).toBe(0);
    expect(row.netTotal).toBeCloseTo(row.totalMerit - row.totalSub, 2);
  });
});
