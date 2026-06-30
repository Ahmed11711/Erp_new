import {
  applyNormalShiftTimes,
  defaultShiftCheckOut,
  fullDayPermissionSavePayload,
  isFullDayPermission,
  normalizeOvernightFingerPrintRecords,
  resolveWorkDayHours,
} from './fingerprint-hours.utils';

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
