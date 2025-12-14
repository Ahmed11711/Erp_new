import { Injectable } from '@angular/core';
import { HttpRequest, HttpHandler, HttpEvent, HttpInterceptor, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { AuthService } from './auth/auth.service';
import { finalize, catchError } from 'rxjs/operators';
import { LoadingService } from './loading.service';
import Swal from 'sweetalert2';

@Injectable()
export class TokenInterceptor implements HttpInterceptor {

  constructor(private authService: AuthService, private loadingService: LoadingService) {}

  intercept(request: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    this.loadingService.showLoading(); // Display loading indicator before request

    const token = this.authService.getToken();

    // Clone request to add the authorization header
    const modifiedRequest = token ? request.clone({
      setHeaders: { Authorization: `Bearer ${token}` }
    }) : request;

    return next.handle(modifiedRequest).pipe(
      catchError((error: HttpErrorResponse) => {
        if (error.status === 403) {
          Swal.fire({
            title: 'غير مسموح',
            icon: 'error',
            showConfirmButton: false,
            timer:1500
          })
        }
        if (error.status === 422) {
          Swal.fire({
            title: error.error.message,
            icon: 'error',
            showConfirmButton: false,
            timer:1500
          })
        }
        // You can handle other errors similarly (e.g., 500, 404, etc.)
        return throwError(error);
      }),
      finalize(() => {
        this.loadingService.hideLoading(); // Hide loading indicator after response
      })
    );
  }
}
