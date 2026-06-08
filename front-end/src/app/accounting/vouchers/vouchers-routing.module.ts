import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';
import { VouchersComponent } from './vouchers.component';

const fin = [...RBAC_ROUTE.financeTeam];

const routes: Routes = [
  {
    path: 'clients',
    component: VouchersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, voucherType: 'client' },
  },
  {
    path: 'suppliers',
    component: VouchersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, voucherType: 'supplier' },
  },
  { path: '', redirectTo: 'clients', pathMatch: 'full' },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class VouchersRoutingModule {}
