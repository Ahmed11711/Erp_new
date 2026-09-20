import { normalizeDashboardUrl, safeInternalReturnUrl } from './dashboard-url.serializer';

describe('dashboard URL helpers', () => {
  it('rewrites a capitalized Dashboard deep link', () => {
    expect(normalizeDashboardUrl('/Dashboard/shipping/shipOrder/46263'))
      .toBe('/dashboard/shipping/shipOrder/46263');
  });

  it('leaves an already-lowercase dashboard path unchanged', () => {
    expect(normalizeDashboardUrl('/dashboard/hr/employee/details/13'))
      .toBe('/dashboard/hr/employee/details/13');
  });

  it('keeps query and hash', () => {
    expect(normalizeDashboardUrl('/DASHBOARD/shipping/listorders?x=1#y'))
      .toBe('/dashboard/shipping/listorders?x=1#y');
  });

  it('rejects open redirects', () => {
    expect(safeInternalReturnUrl('https://evil.example/phish')).toBe('/dashboard');
    expect(safeInternalReturnUrl('//evil.example')).toBe('/dashboard');
    expect(safeInternalReturnUrl('/admin-other')).toBe('/dashboard');
    expect(safeInternalReturnUrl('/dashboard/shipping/shipOrder/1'))
      .toBe('/dashboard/shipping/shipOrder/1');
  });
});
