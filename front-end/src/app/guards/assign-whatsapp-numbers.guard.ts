import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';
import { RbacService } from '../core/rbac/rbac.service';

/** تعيين أرقام الواتساب — صلاحية whatsapp.assign_numbers أو إدارة النظام */
export const assignWhatsAppNumbersGuard: CanActivateFn = () => {
  const rbac = inject(RbacService);
  const router = inject(Router);
  if (rbac.canAny(['whatsapp.assign_numbers', 'system.rbac'])) {
    return true;
  }
  router.navigate(['/dashboard']);
  return false;
};
