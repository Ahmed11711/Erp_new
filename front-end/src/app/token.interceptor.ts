import { Injectable, Injector } from '@angular/core';
import { HttpRequest, HttpHandler, HttpEvent, HttpInterceptor, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { AuthService, AUTH_SKIP_REFRESH_HEADER } from './auth/auth.service';
import { catchError, finalize, switchMap } from 'rxjs/operators';
import { LoadingService } from './loading.service';
import { SystemLockService } from './system-lock/system-lock.service';
import Swal from 'sweetalert2';

@Injectable()
export class TokenInterceptor implements HttpInterceptor {

  constructor(
    private authService: AuthService,
    private loadingService: LoadingService,
    private injector: Injector,
  ) {}

  intercept(request: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    const skipGlobalLoading = this.shouldSkipGlobalLoading(request);
    if (!skipGlobalLoading) {
      this.loadingService.showLoading();
    }

    const bypassRefresh = this.shouldBypassAuthRefresh(request);
    const chain$ = bypassRefresh
      ? next.handle(this.attachToken(request))
      : this.authService.ensureFreshAccessToken().pipe(
          switchMap((token) => next.handle(this.attachToken(request, token))),
          catchError((error: HttpErrorResponse) => this.retryAfterRefreshOrExpire(error, request, next))
        );

    return chain$.pipe(
      catchError((error: HttpErrorResponse) => {
        this.showClientErrors(error);
        return throwError(error);
      }),
      finalize(() => {
        if (!skipGlobalLoading) {
          this.loadingService.hideLoading();
        }
      })
    );
  }

  private retryAfterRefreshOrExpire(
    error: HttpErrorResponse,
    request: HttpRequest<unknown>,
    next: HttpHandler
  ): Observable<HttpEvent<unknown>> {
    if (error.status !== 401) {
      return throwError(error);
    }
    return this.authService.refreshAccessToken().pipe(
      switchMap((token) => next.handle(this.attachToken(request, token))),
      catchError((refreshErr: HttpErrorResponse) => {
        if (!(refreshErr instanceof HttpErrorResponse) || refreshErr.status === 401) {
          this.authService.handleSessionExpired();
        }
        return throwError(refreshErr);
      })
    );
  }

  private attachToken(request: HttpRequest<unknown>, token?: string | false | null): HttpRequest<unknown> {
    // Laravel يعتمد على هذين الترويستين ليردّ 401 JSON بدل تحويل صفحة (الذي يظهر كـ 500).
    const setHeaders: Record<string, string> = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    const resolved = token || this.authService.peekAccessToken();
    if (resolved) {
      setHeaders['Authorization'] = `Bearer ${resolved}`;
    }
    return request.clone({ setHeaders });
  }

  private shouldBypassAuthRefresh(request: HttpRequest<unknown>): boolean {
    if (request.headers.get(AUTH_SKIP_REFRESH_HEADER) === '1') {
      return true;
    }
    return /\/auth\/(login|refresh)(?:\?|$)/.test(request.url || '');
  }

  /**
   * غطاء «تحميل...» العام يُحجز للحفظ/الحذف البطيء فقط.
   * فتح الصفحات = GET (وقراءات الجلسة) فلا يجب أن يوقف الواجهة.
   */
  private shouldSkipGlobalLoading(request: HttpRequest<unknown>): boolean {
    if (request.headers.get('X-Skip-Global-Loading') === '1') {
      return true;
    }
    const method = (request.method || 'GET').toUpperCase();
    if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
      return true;
    }
    return /\/auth\/(login|refresh|me)(?:\?|$)/.test(request.url || '');
  }

  private showClientErrors(error: HttpErrorResponse): void {
    if (error.status === 503 && error.error?.code === 'SYSTEM_LOCKED') {
      this.injector.get(SystemLockService).applyFromHttpError(error);
      return;
    }
    if (error.status === 403) {
      Swal.fire({
        title: 'غير مسموح',
        icon: 'error',
        showConfirmButton: false,
        timer: 1500
      });
    }
    if (error.status === 422) {
      Swal.fire({
        title: error.error.message,
        icon: 'error',
        showConfirmButton: false,
        timer: 1500
      });
    }
  }
}
