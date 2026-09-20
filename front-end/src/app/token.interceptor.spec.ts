import { HTTP_INTERCEPTORS, HttpClient, HttpErrorResponse } from '@angular/common/http';
import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { AuthService } from './auth/auth.service';
import { LoadingService } from './loading.service';
import { TokenInterceptor } from './token.interceptor';

describe('TokenInterceptor', () => {
  let http: HttpClient;
  let httpMock: HttpTestingController;
  let auth: jasmine.SpyObj<AuthService>;
  let loading: { showLoading: jasmine.Spy; hideLoading: jasmine.Spy };

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', [
      'peekAccessToken',
      'ensureFreshAccessToken',
      'refreshAccessToken',
      'handleSessionExpired',
    ]);
    auth.peekAccessToken.and.returnValue('old-token');
    auth.ensureFreshAccessToken.and.returnValue(of('old-token'));
    loading = jasmine.createSpyObj('LoadingService', ['showLoading', 'hideLoading']);

    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
      providers: [
        { provide: AuthService, useValue: auth },
        { provide: LoadingService, useValue: loading },
        { provide: HTTP_INTERCEPTORS, useClass: TokenInterceptor, multi: true },
      ],
    });
    http = TestBed.inject(HttpClient);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('should be created', () => {
    const interceptor = TestBed.inject(HTTP_INTERCEPTORS).find((item) => item instanceof TokenInterceptor);
    expect(interceptor).toBeTruthy();
  });

  it('retries the request after a 401 when refresh succeeds', () => {
    auth.refreshAccessToken.and.returnValue(of('new-token'));

    let body = '';
    http.get('/api/offers').subscribe((res: any) => { body = res.ok; });

    const first = httpMock.expectOne('/api/offers');
    expect(first.request.headers.get('Authorization')).toBe('Bearer old-token');
    first.flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });

    const retry = httpMock.expectOne('/api/offers');
    expect(retry.request.headers.get('Authorization')).toBe('Bearer new-token');
    retry.flush({ ok: 'saved' });

    expect(body).toBe('saved');
    expect(auth.handleSessionExpired).not.toHaveBeenCalled();
  });

  it('expires the session when refresh fails after 401', () => {
    auth.refreshAccessToken.and.returnValue(
      throwError(() => new HttpErrorResponse({ status: 401, statusText: 'Unauthorized' }))
    );

    http.get('/api/offers').subscribe({
      next: () => fail('should error'),
      error: (err) => expect(err.status).toBe(401),
    });

    httpMock.expectOne('/api/offers').flush({ message: 'Unauthenticated.' }, { status: 401, statusText: 'Unauthorized' });
    expect(auth.handleSessionExpired).toHaveBeenCalled();
  });

  it('does not show the global overlay for GET page loads', () => {
    http.get('/api/orders').subscribe();
    httpMock.expectOne('/api/orders').flush([]);
    expect(loading.showLoading).not.toHaveBeenCalled();
    expect(loading.hideLoading).not.toHaveBeenCalled();
  });

  it('shows the global overlay for POST mutations', () => {
    http.post('/api/orders', {}).subscribe();
    httpMock.expectOne('/api/orders').flush({});
    expect(loading.showLoading).toHaveBeenCalled();
    expect(loading.hideLoading).toHaveBeenCalled();
  });

  it('does not refresh a failed login request', () => {
    auth.ensureFreshAccessToken.and.returnValue(of(false));
    auth.peekAccessToken.and.returnValue(false);

    http.post('/api/auth/login', {}).subscribe({
      next: () => fail('should error'),
      error: (err) => expect(err.status).toBe(401),
    });

    httpMock.expectOne('/api/auth/login').flush({ message: 'bad' }, { status: 401, statusText: 'Unauthorized' });
    expect(auth.refreshAccessToken).not.toHaveBeenCalled();
    expect(auth.handleSessionExpired).not.toHaveBeenCalled();
  });
});
