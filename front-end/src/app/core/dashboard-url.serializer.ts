import { DefaultUrlSerializer, UrlSerializer, UrlTree } from '@angular/router';

/**
 * Chrome / WhatsApp / Windows sometimes open `/Dashboard/...` (D كبيرة).
 * المسار المعرّف في Angular هو `dashboard` بحروف صغيرة، وعدم التطابق يترك الصفحة بيضاء.
 */
export function normalizeDashboardUrl(url: string): string {
  if (!url) {
    return url;
  }
  const hashIdx = url.indexOf('#');
  const hash = hashIdx >= 0 ? url.slice(hashIdx) : '';
  const beforeHash = hashIdx >= 0 ? url.slice(0, hashIdx) : url;
  const qIdx = beforeHash.indexOf('?');
  const query = qIdx >= 0 ? beforeHash.slice(qIdx) : '';
  const path = qIdx >= 0 ? beforeHash.slice(0, qIdx) : beforeHash;
  const normalizedPath = path.replace(/^\/Dashboard(?=\/|$)/i, '/dashboard');
  return normalizedPath + query + hash;
}

export class DashboardUrlSerializer extends DefaultUrlSerializer implements UrlSerializer {
  override parse(url: string): UrlTree {
    return super.parse(normalizeDashboardUrl(url));
  }
}

export function safeInternalReturnUrl(raw: string | null | undefined, fallback = '/dashboard'): string {
  if (typeof raw !== 'string') {
    return fallback;
  }
  const trimmed = raw.trim();
  if (!trimmed.startsWith('/') || trimmed.startsWith('//') || trimmed.includes('://')) {
    return fallback;
  }
  const path = normalizeDashboardUrl(trimmed).split('?')[0] || '';
  if (path !== '/dashboard' && !path.startsWith('/dashboard/')) {
    return fallback;
  }
  return normalizeDashboardUrl(trimmed);
}
