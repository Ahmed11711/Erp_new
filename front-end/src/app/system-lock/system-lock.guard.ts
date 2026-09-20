import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivate, CanActivateChild, Router, RouterStateSnapshot, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { SYSTEM_UNAVAILABLE_PATH, SystemLockService } from './system-lock.service';

@Injectable({
  providedIn: 'root'
})
export class SystemLockGuard implements CanActivate, CanActivateChild {

  constructor(
    private lock: SystemLockService,
    private router: Router,
  ) {}

  canActivate(_route: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<boolean | UrlTree> {
    return this.allow(state.url);
  }

  canActivateChild(_child: ActivatedRouteSnapshot, state: RouterStateSnapshot): Observable<boolean | UrlTree> {
    return this.allow(state.url);
  }

  private allow(url: string): Observable<boolean | UrlTree> {
    const decide = () => {
      if (this.lock.isRestricted) {
        return this.lock.isLockPreviewUrl(url)
          ? true
          : this.router.createUrlTree([SYSTEM_UNAVAILABLE_PATH]);
      }
      if (this.lock.isUnavailableAlias(url)) {
        return this.router.createUrlTree(['/dashboard']);
      }
      return true;
    };

    return this.lock.refreshIfStale().pipe(
      map(() => decide()),
      catchError(() => of(true))
    );
  }
}
