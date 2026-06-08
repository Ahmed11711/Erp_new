import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SafesComponent } from './safes.component';
import { SafeDepositWithdrawComponent } from './safe-deposit-withdraw.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const d = { rbacPermissions: [...RBAC_ROUTE.financeTeam] };

const routes: Routes = [
  { path: '', component: SafesComponent, canActivate: [departmentGuard], data: d },
  { path: 'deposit-withdraw', component: SafeDepositWithdrawComponent, canActivate: [departmentGuard], data: d },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class SafesRoutingModule {}
