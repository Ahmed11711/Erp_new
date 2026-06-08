import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BanksComponent } from './banks.component';
import { BankTransferComponent } from './bank-transfer.component';
import { BankSafeTransferComponent } from './bank-safe-transfer.component';
import { BankDepositWithdrawComponent } from './bank-deposit-withdraw.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const d = { rbacPermissions: [...RBAC_ROUTE.financeTeam] };

const routes: Routes = [
  { path: '', component: BanksComponent, canActivate: [departmentGuard], data: d },
  { path: 'transfer', component: BankTransferComponent, canActivate: [departmentGuard], data: d },
  { path: 'transfer-to-safe', component: BankSafeTransferComponent, canActivate: [departmentGuard], data: d },
  { path: 'transfer-from-safe', component: BankSafeTransferComponent, canActivate: [departmentGuard], data: d },
  { path: 'deposit-withdraw', component: BankDepositWithdrawComponent, canActivate: [departmentGuard], data: d },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class BanksRoutingModule {}
