import { CanActivateFn, Router } from '@angular/router';
import { RbacService } from '../core/rbac/rbac.service';
import { inject } from '@angular/core';
import { SystemLockService } from '../system-lock/system-lock.service';

/**
 * حارس موحد بالصلاحيات فقط: يتطلب `data.rbacPermissions` غير فارغ ويمتلك المستخدم أياً منها (canAny).
 * لم يعد استخدام الأقسام في التوجيه — صِغ الأدوار والقوالب لتمنح الـ slug المناسب.
 */
export const departmentGuard: CanActivateFn = (route) => {
  const rbac = inject(RbacService);
  const router = inject(Router);
  if (inject(SystemLockService).isRestricted) {
    return true;
  }
  const rbacPerms = route.data['rbacPermissions'] as string[] | undefined;

  const slugs = Array.isArray(rbacPerms)
    ? rbacPerms.map((p) => (typeof p === 'string' ? p.trim() : '')).filter((p) => p !== '')
    : [];

  if (slugs.length > 0 && rbac.canAny(slugs)) {
    return true;
  }

  router.navigate(['/dashboard']);
  return false;
};
