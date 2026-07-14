import {
  applyNormalShiftTimes,
  baseHourPrice,
  defaultShiftCheckOut,
  deductionMultiplier,
  fullDayPermissionSavePayload,
  isFullDayPermission,
  normalizeOvernightFingerPrintRecords,
  overtimeDeductionRate,
  extraDayMeritAmountAction,
  extraDayMeritAmount,
  EXTRA_DAY_WORKING_DAYS,
  OVERTIME_DEDUCTION_MULTIPLIER,
  resolveWorkDayHours,
  WORKING_DAYS_PER_MONTH,
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

  it('extraDayMeritAmountAction uses 30 working days for add-extra-day button only', () => {
    expect(extraDayMeritAmountAction(22000, 8)).toBeCloseTo((22000 / 30) * 1.5, 5);
    expect(extraDayMeritAmountAction(6000, 9)).toBeCloseTo((6000 / 30) * 1.5, 5);
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
});
