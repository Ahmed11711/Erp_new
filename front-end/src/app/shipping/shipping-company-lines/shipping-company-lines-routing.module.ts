import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';
import { ShippingCompanyLinesComponent } from './shipping-company-lines.component';

const routes: Routes = [
  {
    path: '',
    component: ShippingCompanyLinesComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.systemAdmin] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ShippingCompanyLinesRoutingModule {}
