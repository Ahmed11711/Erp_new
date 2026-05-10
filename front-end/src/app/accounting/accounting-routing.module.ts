import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {
    path: 'general-accounts',
    loadChildren: () => import('./general-accounts/general-accounts.module').then(m => m.GeneralAccountsModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'accounting-tree',
    loadChildren: () => import('./accounting-tree/accounting-tree.module').then(m => m.AccountingTreeModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'cost-centers',
    loadChildren: () => import('./cost-centers/cost-centers.module').then(m => m.CostCentersModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'vouchers',
    loadChildren: () => import('./vouchers/vouchers.module').then(m => m.VouchersModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'banks',
    loadChildren: () => import('./banks/banks.module').then(m => m.BanksModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },
  {
    path: 'safes',
    loadChildren: () => import('./safes/safes.module').then(m => m.SafesModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },
  {
    path: 'fixed-assets',
    loadChildren: () => import('./fixed-assets/fixed-assets.module').then(m => m.FixedAssetsModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'daily-entries',
    loadChildren: () => import('./daily-entries/daily-entries.module').then(m => m.DailyEntriesModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'daily-ledger-report',
    loadChildren: () => import('./daily-ledger-report/daily-ledger-report.module').then(m => m.DailyLedgerReportModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'trial-balance',
    loadChildren: () => import('./trial-balance/trial-balance.module').then(m => m.TrialBalanceModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },
  {
    path: 'account-statement',
    loadChildren: () => import('./account-statement/account-statement.module').then(m => m.AccountStatementModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  },
  {
    path: 'cash-transaction',
    loadChildren: () => import('./cash-transaction/cash-transaction.module').then(m => m.CashTransactionModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },
  {
    path: 'bank-transaction',
    loadChildren: () => import('./bank-transaction/bank-transaction.module').then(m => m.BankTransactionModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },
  {
    path: 'service-transaction',
    loadChildren: () => import('./service-transaction/service-transaction.module').then(m => m.ServiceTransactionModule),
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Financial Accounts'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AccountingRoutingModule { }

