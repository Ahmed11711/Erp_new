import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { safeInternalReturnUrl } from '../core/dashboard-url.serializer';

@Injectable({
  providedIn: 'root'
})
export class AdminGuard implements CanActivate {

  constructor(private router: Router , private loginService:AuthService) {}

  canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): boolean {
    const token = this.loginService.getToken();
    if (token) {
      return true;
    }
    const returnUrl = safeInternalReturnUrl(state.url);
    this.router.navigate([''], { queryParams: { returnUrl } });
    return false;
  }
}
