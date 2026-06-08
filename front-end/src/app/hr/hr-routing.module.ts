import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddEmployeeComponent } from './add-employee/add-employee.component';
import { EmployeeComponent } from './employee/employee.component';
import { PayrollComponent } from './payroll/payroll.component';
import { ReviewAbsencesComponent } from './review-absences/review-absences.component';
import { EditEmployeeComponent } from './edit-employee/edit-employee.component';
import { EmployeeDetailsComponent } from './employee-details/employee-details.component';
import { WorkingHoursComponent } from './working-hours/working-hours.component';
import { WorkingHoursDetailsComponent } from './working-hours-details/working-hours-details.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const hr = [...RBAC_ROUTE.hrWide];

const routes: Routes = [
  { path: 'addemployee', component: AddEmployeeComponent, canActivate: [departmentGuard], data: { rbacPermissions: hr } },
  { path: 'employee', component: EmployeeComponent, canActivate: [departmentGuard], data: { rbacPermissions: hr } },
  { path: 'payroll', component: PayrollComponent, canActivate: [departmentGuard], data: { rbacPermissions: hr } },
  { path: 'reviewabsencess', component: ReviewAbsencesComponent, canActivate: [departmentGuard], data: { rbacPermissions: hr } },
  {
    path: 'employee/edit/:id',
    component: EditEmployeeComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: hr },
  },
  {
    path: 'employee/details/:id',
    component: EmployeeDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: hr },
  },
  { path: 'workinghours', component: WorkingHoursComponent, canActivate: [departmentGuard], data: { rbacPermissions: hr } },
  {
    path: 'workinghoursdetails/:id',
    component: WorkingHoursDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: hr },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class HrRoutingModule {}
