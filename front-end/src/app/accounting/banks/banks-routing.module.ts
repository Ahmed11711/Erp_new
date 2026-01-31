import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { BanksComponent } from './banks.component';
import { BankTransferComponent } from './bank-transfer.component';
import { BankSafeTransferComponent } from './bank-safe-transfer.component';
import { BankDepositWithdrawComponent } from './bank-deposit-withdraw.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: BanksComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'transfer',
    component: BankTransferComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'transfer-to-safe',
    component: BankSafeTransferComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'transfer-from-safe',
    component: BankSafeTransferComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'deposit-withdraw',
    component: BankDepositWithdrawComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class BanksRoutingModule { }

