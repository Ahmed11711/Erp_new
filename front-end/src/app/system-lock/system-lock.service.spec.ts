import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { AuthService } from 'src/app/auth/auth.service';
import { environment } from 'src/env/env';
import { SYSTEM_LOCK_DEFAULT_MESSAGE, SystemLockService } from './system-lock.service';

describe('SystemLockService', () => {
  let service: SystemLockService;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        SystemLockService,
        AuthService,
        { provide: CookieService, useValue: { get: () => '', delete: () => undefined } },
        { provide: Router, useValue: { url: '/dashboard', navigateByUrl: () => Promise.resolve(true) } },
      ],
    });
    service = TestBed.inject(SystemLockService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  it('starts unlocked', () => {
    expect(service.locked).toBeFalse();
    expect(service.isRestricted).toBeFalse();
    expect(service.message).toBe(SYSTEM_LOCK_DEFAULT_MESSAGE);
  });

  it('applies a restricted payload without navigating when asked', () => {
    service.applyStatus({
      locked: true,
      restricted: true,
      can_lock: false,
      can_unlock: false,
      can_control: false,
      message: 'عطل مؤقت',
      show_message: true,
      exempt_user_ids: [3],
    }, false);

    expect(service.locked).toBeTrue();
    expect(service.isRestricted).toBeTrue();
    expect(service.canControl).toBeFalse();
    expect(service.message).toBe('عطل مؤقت');
  });

  it('loads status from the API', () => {
    TestBed.inject(AuthService).saveTolocalStorage({ access_token: 'abc' });

    let locked = false;
    service.refresh().subscribe((res) => { locked = res.locked; });

    const req = http.expectOne(`${environment.Url}/system-lock`);
    expect(req.request.method).toBe('GET');
    req.flush({
      locked: true,
      restricted: false,
      can_lock: true,
      can_unlock: true,
      can_control: true,
      message: 'صيانة',
      exempt_user_ids: [],
      locked_by: { id: 1, name: 'Admin' },
      locked_at: '2026-09-11T10:00:00+03:00',
    });

    expect(locked).toBeTrue();
    expect(service.canControl).toBeTrue();
    expect(service.isRestricted).toBeFalse();
  });

  it('treats the orders list as the lock preview page', () => {
    const router = TestBed.inject(Router) as unknown as { url: string };
    router.url = '/dashboard/shipping/listorders';
    expect(service.isLockPreviewUrl()).toBeTrue();
    expect(service.isUnavailableAlias()).toBeFalse();

    router.url = '/dashboard/unavailable';
    expect(service.isLockPreviewUrl()).toBeTrue();
    expect(service.isUnavailableAlias()).toBeTrue();

    router.url = '/dashboard';
    expect(service.isLockPreviewUrl()).toBeFalse();
  });
});
