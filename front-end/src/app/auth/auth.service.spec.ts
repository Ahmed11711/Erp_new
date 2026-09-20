import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { environment } from 'src/env/env';
import { AUTH_SESSION_EXPIRED_FLAG, AuthService } from './auth.service';

function fakeJwt(exp: number): string {
  const header = btoa(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const body = btoa(JSON.stringify({ exp, sub: 1 }));
  return `${header}.${body}.sig`;
}

describe('AuthService', () => {
  let service: AuthService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        AuthService,
        { provide: CookieService, useValue: { get: () => '', delete: () => undefined } },
        { provide: Router, useValue: { url: '/' } },
      ],
    });
    service = TestBed.inject(AuthService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
    sessionStorage.clear();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('returns false for a missing token even on dashboard routes', () => {
    const router = TestBed.inject(Router) as { url: string };
    router.url = '/dashboard';
    expect(service.getToken()).toBeFalse();
  });

  it('returns the stored token without reloading', () => {
    service.saveTolocalStorage({ access_token: 'abc' });
    expect(service.peekAccessToken()).toBe('abc');
  });

  it('refreshes once when the token is near expiry and shares the in-flight request', () => {
    const expiring = fakeJwt(Math.floor(Date.now() / 1000) + 30);
    const next = fakeJwt(Math.floor(Date.now() / 1000) + 3600);
    service.saveTolocalStorage({ access_token: expiring, user: 'sales' });

    let first = '';
    let second = '';
    service.ensureFreshAccessToken().subscribe((token) => { first = token as string; });
    service.ensureFreshAccessToken().subscribe((token) => { second = token as string; });

    const reqs = http.match(`${environment.Url}/auth/refresh`);
    expect(reqs.length).toBe(1);
    reqs[0].flush({ access_token: next, user: 'sales' });

    expect(first).toBe(next);
    expect(second).toBe(next);
    expect(service.peekAccessToken()).toBe(next);
  });

  it('shares a single /auth/me request for department and permissions sync', () => {
    service.saveTolocalStorage({ access_token: 'abc', user: 'sales' });

    let first = false;
    let second = false;
    service.syncSessionDepartmentFromServer().subscribe((ok) => { first = ok; });
    service.syncSessionPermissionsFromServer().subscribe((ok) => { second = ok; });

    const reqs = http.match(`${environment.Url}/auth/me`);
    expect(reqs.length).toBe(1);
    expect(reqs[0].request.headers.get('X-Skip-Global-Loading')).toBe('1');
    reqs[0].flush({
      department: 'admin',
      rbac: { effective_permission_keys: ['orders.view'] },
    });

    expect(first).toBeTrue();
    expect(second).toBeTrue();
    expect(service.getUser()).toBe('admin');
  });

  it('marks the session expired flag and clears storage', () => {
    service.saveTolocalStorage({ access_token: 'abc' });
    service.handleSessionExpired();
    expect(service.peekAccessToken()).toBeFalse();
    expect(sessionStorage.getItem(AUTH_SESSION_EXPIRED_FLAG)).toBe('1');
    expect(service.consumeSessionExpiredNotice()).toBeTrue();
    expect(service.consumeSessionExpiredNotice()).toBeFalse();
  });
});
