import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const financeTeam = { rbacPermissions: [...RBAC_ROUTE.financeTeam] };

const routes: Routes = [
  {
    path: 'general-accounts',
    loadChildren: () => import('./general-accounts/general-accounts.module').then((m) => m.GeneralAccountsModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'accounting-tree',
    loadChildren: () => import('./accounting-tree/accounting-tree.module').then((m) => m.AccountingTreeModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'cost-centers',
    loadChildren: () => import('./cost-centers/cost-centers.module').then((m) => m.CostCentersModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'vouchers',
    loadChildren: () => import('./vouchers/vouchers.module').then((m) => m.VouchersModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'banks',
    loadChildren: () => import('./banks/banks.module').then((m) => m.BanksModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'safes',
    loadChildren: () => import('./safes/safes.module').then((m) => m.SafesModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'fixed-assets',
    loadChildren: () => import('./fixed-assets/fixed-assets.module').then((m) => m.FixedAssetsModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'daily-entries',
    loadChildren: () => import('./daily-entries/daily-entries.module').then((m) => m.DailyEntriesModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'daily-ledger-report',
    loadChildren: () => import('./daily-ledger-report/daily-ledger-report.module').then((m) => m.DailyLedgerReportModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'trial-balance',
    loadChildren: () => import('./trial-balance/trial-balance.module').then((m) => m.TrialBalanceModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'account-statement',
    loadChildren: () => import('./account-statement/account-statement.module').then((m) => m.AccountStatementModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'cash-transaction',
    loadChildren: () => import('./cash-transaction/cash-transaction.module').then((m) => m.CashTransactionModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'bank-transaction',
    loadChildren: () => import('./bank-transaction/bank-transaction.module').then((m) => m.BankTransactionModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
  {
    path: 'service-transaction',
    loadChildren: () => import('./service-transaction/service-transaction.module').then((m) => m.ServiceTransactionModule),
    canActivate: [departmentGuard],
    data: financeTeam,
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class AccountingRoutingModule {}
