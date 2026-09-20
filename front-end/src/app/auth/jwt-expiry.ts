/** Seconds before JWT `exp` at which the client should refresh. */
export const AUTH_REFRESH_LEEWAY_SECONDS = 300;

export function decodeJwtPayload(token: string): Record<string, unknown> | null {
  try {
    const parts = token.split('.');
    if (parts.length < 2 || !parts[1]) {
      return null;
    }
    const base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    return JSON.parse(atob(padded)) as Record<string, unknown>;
  } catch {
    return null;
  }
}

export function getJwtExpiryUnix(token: string): number | null {
  const exp = decodeJwtPayload(token)?.exp;
  return typeof exp === 'number' && Number.isFinite(exp) ? exp : null;
}

export function isJwtExpiringSoon(
  token: string,
  nowUnix = Math.floor(Date.now() / 1000),
  leewaySeconds = AUTH_REFRESH_LEEWAY_SECONDS
): boolean {
  const exp = getJwtExpiryUnix(token);
  if (exp == null) {
    return false;
  }
  return exp <= nowUnix + leewaySeconds;
}
