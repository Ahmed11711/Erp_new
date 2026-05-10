import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { catchError, map, of, tap } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

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
    if (this.cookie.get('magalis')) {
      this.cookie.delete('magalis', '/');
    }
    const expirationDate = new Date();
    expirationDate.setTime(expirationDate.getTime() + data.expires_in * 60000);
    this.cookie.set('magalis', JSON.stringify(data), expirationDate, '/', undefined, true, 'Strict');
  }

  getToken(): string | boolean {
    const data = this.cookie.get('magalis') || '';
    const currentUrl = this.route.url;
    if (currentUrl !== '/' && data === '') {
      location.reload();
    }
    if (data !== '') {
      const parsedData = JSON.parse(data);
      return parsedData.access_token;
    }
    return false;
  }

  getPermission(): any {
    const data = this.cookie.get('magalis') || '';
    if (data !== '') {
      const parsedData = JSON.parse(data);
      const permission = parsedData.permissions.map((elm: string) => elm.toLowerCase());
      return permission;
    }
    return false;
  }

  getUser(): any {
    const data = this.cookie.get('magalis') || '';
    if (data !== '') {
      try {
        const parsedData = JSON.parse(data);
        const u = parsedData?.user;
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
    }
    return false;
  }

  /** تعاد قراءة `department` من السيرفر وتحديث الحقل في الكوكي (مهم بعد تعديل القسم يدوياً في DB). */
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

  private patchStoredUserDepartment(department: string): void {
    const raw = this.cookie.get('magalis');
    if (!raw) {
      return;
    }
    try {
      const parsed = JSON.parse(raw);
      const next = department.trim();
      const prev =
        typeof parsed.user === 'string' ? parsed.user.trim() : String(parsed?.user ?? '').trim();
      if (prev === next) {
        return;
      }
      parsed.user = next;
      const exp = new Date();
      const minutes = Number(parsed.expires_in);
      const ttlMs = Number.isFinite(minutes) && minutes > 0 ? minutes * 60_000 : 7 * 24 * 60 * 60 * 1000;
      exp.setTime(exp.getTime() + ttlMs);
      this.cookie.set('magalis', JSON.stringify(parsed), exp, '/', undefined, true, 'Strict');
    } catch {
      /* noop */
    }
  }

  userName(): any {
    const data = this.cookie.get('magalis') || '';
    if (data !== '') {
      const parsedData = JSON.parse(data);
      return parsedData.name;
    }
    return false;
  }

  logOut(): void {
    this.cookie.delete('magalis', '/');
  }
}
