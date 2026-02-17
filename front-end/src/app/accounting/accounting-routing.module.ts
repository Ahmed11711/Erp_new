import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {
    path: 'general-accounts',
    loadChildren: () => import('./general-accounts/general-accounts.module').then(m => m.GeneralAccountsModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'accounting-tree',
    loadChildren: () => import('./accounting-tree/accounting-tree.module').then(m => m.AccountingTreeModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'cost-centers',
    loadChildren: () => import('./cost-centers/cost-centers.module').then(m => m.CostCentersModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'vouchers',
    loadChildren: () => import('./vouchers/vouchers.module').then(m => m.VouchersModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'banks',
    loadChildren: () => import('./banks/banks.module').then(m => m.BanksModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'safes',
    loadChildren: () => import('./safes/safes.module').then(m => m.SafesModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'fixed-assets',
    loadChildren: () => import('./fixed-assets/fixed-assets.module').then(m => m.FixedAssetsModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'daily-entries',
    loadChildren: () => import('./daily-entries/daily-entries.module').then(m => m.DailyEntriesModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'daily-ledger-report',
    loadChildren: () => import('./daily-ledger-report/daily-ledger-report.module').then(m => m.DailyLedgerReportModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'trial-balance',
    loadChildren: () => import('./trial-balance/trial-balance.module').then(m => m.TrialBalanceModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AccountingRoutingModule { }

