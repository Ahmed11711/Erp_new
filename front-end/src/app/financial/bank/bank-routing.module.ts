import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const routes: Routes = [
  {
    path: 'receive-from-client',
    redirectTo: '/dashboard/financial/cash/receive',
    pathMatch: 'full',
  },
  {
    path: 'pay-to-supplier',
    component: PayToSupplierComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.financeTeam] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BankRoutingModule {}
