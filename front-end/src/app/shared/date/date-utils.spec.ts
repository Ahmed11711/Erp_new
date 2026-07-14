import {
  formatDdMmYyyy,
  isValidDateParts,
  parseDdMmYyyy,
} from './date-utils';

describe('date-utils (DD/MM/YYYY)', () => {
  it('parses 05/07/2026 as 5 July 2026', () => {
    const date = parseDdMmYyyy('05/07/2026');
    expect(date).not.toBeNull();
    expect(date!.getDate()).toBe(5);
    expect(date!.getMonth()).toBe(6);
    expect(date!.getFullYear()).toBe(2026);
  });

  it('formats as DD/MM/YYYY', () => {
    const date = parseDdMmYyyy('05/07/2026')!;
    expect(formatDdMmYyyy(date)).toBe('05/07/2026');
  });

  it('rejects 32/01/2026', () => {
    expect(parseDdMmYyyy('32/01/2026')).toBeNull();
    expect(isValidDateParts(32, 1, 2026)).toBeFalse();
  });

  it('rejects 31/02/2026', () => {
    expect(parseDdMmYyyy('31/02/2026')).toBeNull();
    expect(isValidDateParts(31, 2, 2026)).toBeFalse();
  });

  it('rejects 15/13/2026', () => {
    expect(parseDdMmYyyy('15/13/2026')).toBeNull();
    expect(isValidDateParts(15, 13, 2026)).toBeFalse();
  });

  it('parses ISO yyyy-MM-dd', () => {
    const date = parseDdMmYyyy('2026-07-05');
    expect(date!.getDate()).toBe(5);
    expect(date!.getMonth()).toBe(6);
  });

  it('returns null for incomplete manual input', () => {
    expect(parseDdMmYyyy('05/07')).toBeNull();
    expect(parseDdMmYyyy('05/07/26')).toBeNull();
  });
});
