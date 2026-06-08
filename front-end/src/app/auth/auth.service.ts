import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { catchError, map, Observable, of, Subject, tap } from 'rxjs';
import { environment } from 'src/env/env';

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
    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(parsed));
    sessionStorage.removeItem(this.STORAGE_KEY);
    if (this.cookie.get('magalis')) {
      this.cookie.delete('magalis', '/');
    }
  }

  getToken(): string | boolean {
    const parsed = this.readPayload();
    const currentUrl = this.route.url;
    const hasToken = typeof parsed?.access_token === 'string' && parsed.access_token !== '';
    if (!this.isLoginShellUrl(currentUrl) && !hasToken) {
      location.reload();
    }
    if (hasToken) {
      return parsed!.access_token as string;
    }
    return false;
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

  /** تعاد قراءة `department` من السيرفر وتحديث الحقل في الجلسة (مهم بعد تعديل القسم يدوياً في DB). */
  syncSessionDepartmentFromServer() {
    const token = this.getToken();
    if (!token) {
      return of(false);
    }
    return this.http.post<{ department?: string }>(`${environment.Url}/auth/me`, {}).pipe(
      map((res) => (typeof res?.department === 'string' ? res.department.trim() : '')),
      tap((dep) => {
        if (dep !== '') {
          this.patchStoredUserDepartment(dep);
        }
      }),
      map((dep) => dep !== ''),
      catchError(() => of(false))
    );
  }

  /**
   * يحدّث الصلاحيات من السيرفر بعد تعيين أدوار أو تجاوزات (بدون إعادة تسجيل الدخول).
   */
  syncSessionPermissionsFromServer(): Observable<boolean> {
    const token = this.getToken();
    if (!token) {
      return of(false);
    }
    return this.http.post<{ rbac?: { effective_permission_keys?: string[] } }>(`${environment.Url}/auth/me`, {}).pipe(
      tap((res) => this.patchStoredPermissions(res)),
      map(() => true),
      catchError(() => of(false))
    );
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

  userName(): any {
    const parsed = this.readPayload();
    if (!parsed?.name) {
      return false;
    }
    return parsed.name;
  }

  logOut(): void {
    if (typeof window !== 'undefined') {
      localStorage.removeItem(this.STORAGE_KEY);
      sessionStorage.removeItem(this.STORAGE_KEY);
    }
    this.cookie.delete('magalis', '/');
  }
}
