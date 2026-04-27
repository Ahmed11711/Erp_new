import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { AuthService } from '../auth/auth.service';

const PERM = 'assign to whatsapp number';

/** صفحة تعيين المستخدمين لأرقام الواتساب — Admin أو صلاحية Spatie: assign to whatsapp number */
export const assignWhatsAppNumbersGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const user = auth.getUser();
  const perms = auth.getPermission();
  const hasPerm = Array.isArray(perms) && perms.includes(PERM);
  if (user === 'Admin' || hasPerm) {
    return true;
  }
  router.navigate(['/dashboard']);
  return false;
};
