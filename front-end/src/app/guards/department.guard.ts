import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../auth/auth.service';
import { inject } from '@angular/core';

export const departmentGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const allowedDepartments: string[] = route.data['allowedDepartments'];
  const user = authService.getUser();

  console.log('DepartmentGuard Check:', { user, allowedDepartments });

  if (allowedDepartments.includes(user) || allowedDepartments.some(d => d.trim() === user?.toString().trim())) {
    return true;
  }
  router.navigate(['/dashboard']);
  return false;
};
