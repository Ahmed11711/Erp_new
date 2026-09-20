import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { catchError, finalize, map, Observable, of, shareReplay, Subject, tap, throwError } from 'rxjs';
import { environment } from 'src/env/env';
import { isJwtExpiringSoon } from './jwt-expiry';

export const AUTH_SESSION_EXPIRED_FLAG = 'magalis_session_expired';
export const AUTH_SKIP_REFRESH_HEADER = 'X-Skip-Auth-Refresh';

type AuthTokenResponse = {
  access_token?: string;
  token_type?: string;
  expires_in?: number;
  user?: unknown;
  name?: unknown;
  permissions?: unknown;
  rbac?: unknown;
  system_lock?: unknown;
};

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  /** يُطلَق بعد تحديث حقول الصلاحيات في الجلسة (ليعيد المكوّن الأب رسم القائمة). */
  readonly permissionsCookieUpdated = new Subject<void>();

  /**
   * استجابة تسجيل الدخول كبيرة (JWT + صلاحيات + rbac) فلا تصلح للكوكي (~4KB).
   * تُخزَّن في localStorage لتُشارَك بين كل التبويبات (sessionStorage معزول لكل تبويب فيطلب إعادة الدخول عند «فتح في تبويب جديد»).
   */
  private readonly STORAGE_KEY = 'magalis_auth';
  private refreshInFlight$: Observable<string> | null = null;
  private sessionExpiredHandled = false;

  constructor(private http: HttpClient, private cookie: CookieService, private route: Router) { }

  login(data: object): any {
    return this.http.post(`${environment.Url}/auth/login`, data)
      .pipe(
        catchError(error => {
          console.error('Login error:', error);
          throw error;
        })
      );
  }

  saveTolocalStorage(data: any): void {
    this.sessionExpiredHandled = false;
    this.writePayload(data as Record<string, unknown>);
  }

  /**
   * مسار تسجيل الدخول قد يكون '' أو '/' حسب التحميل الكسول؛ بدون هذا الشرط قد يحدث reload قبل إكمال الدخول.
   */
  private isLoginShellUrl(url: string): boolean {
    const path = url.split('?')[0] || '';
    return path === '' || path === '/';
  }

  private readPayload(): Record<string, unknown> | null {
    if (typeof window === 'undefined') {
      return null;
    }
    try {
      const fromLocal = localStorage.getItem(this.STORAGE_KEY);
      if (fromLocal) {
        return JSON.parse(fromLocal) as Record<string, unknown>;
      }
      const fromSession = sessionStorage.getItem(this.STORAGE_KEY);
      if (fromSession) {
        const parsed = JSON.parse(fromSession) as Record<string, unknown>;
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(parsed));
        sessionStorage.removeItem(this.STORAGE_KEY);
        return parsed;
      }
      const legacy = this.cookie.get('magalis');
      if (legacy) {
        localStorage.setItem(this.STORAGE_KEY, legacy);
        this.cookie.delete('magalis', '/');
        return JSON.parse(legacy) as Record<string, unknown>;
      }
    } catch {
      /* noop */
    }
    return null;
  }

  private writePayload(parsed: Record<string, unknown>): void {
    if (typeof window === 'undefined') {
      return;
    }
    const persist = (payload: Record<string, unknown>): boolean => {
      try {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(payload));
        return true;
      } catch {
        return false;
      }
    };
    if (!persist(parsed)) {
      const slim = { ...parsed };
      delete slim.rbac;
      persist(slim);
    }
    sessionStorage.removeItem(this.STORAGE_KEY);
    if (this.cookie.get('magalis')) {
      this.cookie.delete('magalis', '/');
    }
  }

  peekAccessToken(): string | false {
    const parsed = this.readPayload();
    const token = parsed?.access_token;
    return typeof token === 'string' && token !== '' ? token : false;
  }

  getToken(): string | boolean {
    // لا تُعد تحميل الصفحة عند غياب التوكن — ذلك يسبب حلقة reload لا نهائية
    // عند فتح /dashboard بدون جلسة (أو عند امتلاء localStorage). الحارس يحوّل لتسجيل الدخول.
    return this.peekAccessToken();
  }

  ensureFreshAccessToken(): Observable<string | false> {
    const token = this.peekAccessToken();
    if (!token) {
      return of(false);
    }
    if (!isJwtExpiringSoon(token)) {
      return of(token);
    }
    return this.refreshAccessToken();
  }

  refreshAccessToken(): Observable<string> {
    const token = this.peekAccessToken();
    if (!token) {
      return throwError(() => new Error('no access token'));
    }
    if (this.refreshInFlight$) {
      return this.refreshInFlight$;
    }
    const headers = new HttpHeaders({
      Authorization: `Bearer ${token}`,
      [AUTH_SKIP_REFRESH_HEADER]: '1',
      'X-Skip-Global-Loading': '1',
    });
    this.refreshInFlight$ = this.http.post<AuthTokenResponse>(`${environment.Url}/auth/refresh`, {}, { headers }).pipe(
      map((res) => {
        const next = typeof res?.access_token === 'string' ? res.access_token : '';
        if (!next) {
          throw new Error('refresh returned empty token');
        }
        this.mergeRefreshedToken(res);
        return next;
      }),
      shareReplay({ bufferSize: 1, refCount: false }),
      finalize(() => {
        this.refreshInFlight$ = null;
      })
    );
    return this.refreshInFlight$;
  }

  handleSessionExpired(): void {
    if (this.sessionExpiredHandled) {
      return;
    }
    this.sessionExpiredHandled = true;
    this.logOut();
    if (typeof window === 'undefined') {
      return;
    }
    try {
      sessionStorage.setItem(AUTH_SESSION_EXPIRED_FLAG, '1');
    } catch {
      /* noop */
    }
    if (!this.isLoginShellUrl(this.route.url)) {
      window.location.assign('/');
    }
  }

  consumeSessionExpiredNotice(): boolean {
    if (typeof window === 'undefined') {
      return false;
    }
    try {
      if (sessionStorage.getItem(AUTH_SESSION_EXPIRED_FLAG) === '1') {
        sessionStorage.removeItem(AUTH_SESSION_EXPIRED_FLAG);
        return true;
      }
    } catch {
      /* noop */
    }
    return false;
  }

  private mergeRefreshedToken(res: AuthTokenResponse): void {
    const parsed = this.readPayload() || {};
    parsed.access_token = res.access_token;
    if (res.token_type != null) {
      parsed.token_type = res.token_type;
    }
    if (res.expires_in != null) {
      parsed.expires_in = res.expires_in;
    }
    if (res.user != null) {
      parsed.user = res.user;
    }
    if (res.name != null) {
      parsed.name = res.name;
    }
    if (res.permissions != null) {
      parsed.permissions = res.permissions;
    }
    if (res.rbac != null) {
      parsed.rbac = res.rbac;
    }
    if (res.system_lock != null) {
      parsed.system_lock = res.system_lock;
    }
    this.writePayload(parsed);
  }

  getPermission(): any {
    const parsed = this.readPayload();
    if (!parsed) {
      return false;
    }
    const list = parsed.permissions;
    if (!Array.isArray(list)) {
      return false;
    }
    return list.map((elm: string) => String(elm).toLowerCase());
  }

  /** Raw JWT/login payload stored for the session (includes rbac snapshot when backend sends it). */
  getStoredAuthPayload(): Record<string, unknown> | null {
    return this.readPayload();
  }

  getUser(): any {
    const parsed = this.readPayload();
    if (!parsed) {
      return false;
    }
    try {
      const u = parsed.user;
      if (typeof u === 'string') {
        const t = u.trim();
        return t === '' ? false : t;
      }
      if (u != null && u !== false && u !== '') {
        const t = String(u).trim();
        return t === '' ? false : t;
      }
    } catch {
      return false;
    }
    return false;
  }

  private sessionSyncInFlight$: Observable<boolean> | null = null;

  /**
   * طلب واحد لـ /auth/me يحدّث القسم والصلاحيات معاً (بدون غطاء تحميل عام).
   */
  syncSessionFromServer(): Observable<boolean> {
    const token = this.peekAccessToken();
    if (!token) {
      return of(false);
    }
    if (this.sessionSyncInFlight$) {
      return this.sessionSyncInFlight$;
    }
    const headers = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });
    this.sessionSyncInFlight$ = this.http.post<{
      department?: string;
      rbac?: { effective_permission_keys?: string[] };
      system_lock?: unknown;
    }>(`${environment.Url}/auth/me`, {}, { headers }).pipe(
      tap((res) => {
        const dep = typeof res?.department === 'string' ? res.department.trim() : '';
        if (dep !== '') {
          this.patchStoredUserDepartment(dep);
        }
        this.patchStoredPermissions(res);
        this.patchStoredSystemLock(res);
      }),
      map(() => true),
      catchError(() => of(false)),
      finalize(() => {
        this.sessionSyncInFlight$ = null;
      }),
      shareReplay({ bufferSize: 1, refCount: false })
    );
    return this.sessionSyncInFlight$;
  }

  /** تعاد قراءة `department` من السيرفر وتحديث الحقل في الجلسة (مهم بعد تعديل القسم يدوياً في DB). */
  syncSessionDepartmentFromServer() {
    return this.syncSessionFromServer();
  }

  /**
   * يحدّث الصلاحيات من السيرفر بعد تعيين أدوار أو تجاوزات (بدون إعادة تسجيل الدخول).
   */
  syncSessionPermissionsFromServer(): Observable<boolean> {
    return this.syncSessionFromServer();
  }

  private patchStoredUserDepartment(department: string): void {
    const parsed = this.readPayload();
    if (!parsed) {
      return;
    }
    try {
      const next = department.trim();
      const prev =
        typeof parsed.user === 'string' ? parsed.user.trim() : String(parsed?.user ?? '').trim();
      if (prev === next) {
        return;
      }
      parsed.user = next;
      this.writePayload(parsed);
    } catch {
      /* noop */
    }
  }

  private patchStoredPermissions(res: { rbac?: { effective_permission_keys?: string[] } }): void {
    const parsed = this.readPayload();
    if (!parsed) {
      return;
    }
    const keys = res?.rbac?.effective_permission_keys;
    if (!Array.isArray(keys)) {
      return;
    }
    try {
      parsed.permissions = keys.map((k) => String(k).toLowerCase());
      if (res.rbac) {
        parsed.rbac = res.rbac as unknown as Record<string, unknown>;
      }
      this.writePayload(parsed);
      this.permissionsCookieUpdated.next();
    } catch {
      /* noop */
    }
  }

  private patchStoredSystemLock(res: { system_lock?: unknown }): void {
    const parsed = this.readPayload();
    if (!parsed || res?.system_lock == null) {
      return;
    }
    try {
      parsed.system_lock = res.system_lock;
      this.writePayload(parsed);
    } catch {
      /* noop */
    }
  }

  userName(): any {
    const parsed = this.readPayload();
    if (!parsed?.name) {
      return false;
    }
    return parsed.name;
  }

  fetchMe(): Observable<{ id?: number; name?: string; department?: string }> {
    return this.http.post<{ id?: number; name?: string; department?: string }>(
      `${environment.Url}/auth/me`,
      {},
      { headers: new HttpHeaders({ 'X-Skip-Global-Loading': '1' }) }
    );
  }

  logOut(): void {
    if (typeof window !== 'undefined') {
      localStorage.removeItem(this.STORAGE_KEY);
      sessionStorage.removeItem(this.STORAGE_KEY);
    }
    this.cookie.delete('magalis', '/');
  }
}
