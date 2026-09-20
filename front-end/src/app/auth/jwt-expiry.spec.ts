import { decodeJwtPayload, getJwtExpiryUnix, isJwtExpiringSoon } from './jwt-expiry';

function fakeJwt(payload: Record<string, unknown>): string {
  const header = btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = btoa(JSON.stringify(payload));
  return `${header}.${body}.sig`;
}

describe('jwt-expiry', () => {
  it('decodes the payload and reads exp', () => {
    const token = fakeJwt({ exp: 1_800_000_000, sub: 7 });
    expect(decodeJwtPayload(token)?.sub).toBe(7);
    expect(getJwtExpiryUnix(token)).toBe(1_800_000_000);
  });

  it('treats a token as expiring when exp is inside the leeway window', () => {
    const now = 1_700_000_000;
    const token = fakeJwt({ exp: now + 60 });
    expect(isJwtExpiringSoon(token, now, 300)).toBeTrue();
    expect(isJwtExpiringSoon(token, now, 30)).toBeFalse();
  });

  it('returns false when the token has no exp claim', () => {
    expect(isJwtExpiringSoon(fakeJwt({ sub: 1 }), 1_700_000_000)).toBeFalse();
    expect(getJwtExpiryUnix('not-a-jwt')).toBeNull();
  });
});
