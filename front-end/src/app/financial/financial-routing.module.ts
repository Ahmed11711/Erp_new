import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ListBanksComponent } from './list-banks/list-banks.component';
import { AddExpenseComponent } from './add-expense/add-expense.component';
import { ExpensesComponent } from './expenses/expenses.component';
import { ExpensesKindComponent } from './expenses-kind/expenses-kind.component';
import { AddIncomeComponent } from './add-income/add-income.component';
import { OtherIncomeComponent } from './other-income/other-income.component';
import { EstatesComponent } from './estates/estates.component';
import { AddEstateComponent } from './add-estate/add-estate.component';
import { DiscountsComponent } from './discounts/discounts.component';
import { AddCommitmentComponent } from './add-commitment/add-commitment.component';
import { IndividualsClientsComponent } from './individuals-clients/individuals-clients.component';
import { CovenantComponent } from './covenant/covenant.component';
import { AddCovenantComponent } from './add-covenant/add-covenant.component';
import { ExpenseDetailsComponent } from './expense-details/expense-details.component';
import { BankDetailsComponent } from './bank-details/bank-details.component';
import { PendingComponent } from './pending/pending.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { BanksMovementsComponent } from './banks-movements/banks-movements.component';
import { IncomeListComponent } from './income-list/income-list.component';
import { CustomerAccountsComponent } from './customer-accounts/customer-accounts.component';
import { SupplierAccountsComponent } from './supplier-accounts/supplier-accounts.component';
import { CustomerAccountDetailsComponent } from './customer-account-details/customer-account-details.component';
import { AssetCategoryComponent } from './asset-category/asset-category.component';
import { AssetSubCategoryComponent } from './asset-sub-category/asset-sub-category.component';
import { AssetSubSubCategoryComponent } from './asset-sub-categoryEnd/asset-sub-category-end.component';
import { ReportNewOrdersComponent } from './V2/report-new-order/report-new-order.component';
import { ReportNewOrdersComponentDetails } from './V2/report-new-order-details/report-new-order-details.component';
import { ServiceAccountsListComponent } from './service-accounts/service-accounts-list/service-accounts-list.component';
import { CashFlowHubComponent } from './cash-flow-hub/cash-flow-hub.component';

const fin = [...RBAC_ROUTE.financeTeam];
const adm = [...RBAC_ROUTE.financeAdminExclusive];

const routes: Routes = [
  {
    path: 'cash-in',
    component: CashFlowHubComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, hub: 'in' },
  },
  {
    path: 'cash-out',
    component: CashFlowHubComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, hub: 'out' },
  },
  { path: 'banks', component: ListBanksComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'addexpense', component: AddExpenseComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'editexpense/:id', component: AddExpenseComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'expenses', component: ExpensesComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'expenseskind', component: ExpensesKindComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'banks-movements', component: BanksMovementsComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'income-list', component: IncomeListComponent, canActivate: [departmentGuard], data: { rbacPermissions: fin } },
  { path: 'addincome', component: AddIncomeComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'otherincome', component: OtherIncomeComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'estates', component: EstatesComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  {
    path: 'estates/category/:id',
    component: AssetCategoryComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: adm },
  },
  {
    path: 'estates/sub-category/:id',
    component: AssetSubCategoryComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: adm },
  },
  {
    path: 'estates/sub-category-end/:id',
    component: AssetSubSubCategoryComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: adm },
  },
  { path: 'addestate', component: AddEstateComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'discounts', component: DiscountsComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'addcimmitment', component: AddCommitmentComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  {
    path: 'individualsclients',
    component: IndividualsClientsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: adm },
  },
  { path: 'covenant', component: CovenantComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'addcovenant', component: AddCovenantComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  {
    path: 'expense_details/:id',
    component: ExpenseDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'bank_details/:id',
    component: BankDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'customer-accounts',
    component: CustomerAccountsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'report-order-new',
    component: ReportNewOrdersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'report-order-new-details',
    component: ReportNewOrdersComponentDetails,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'customer-accounts/customer-account-details',
    component: CustomerAccountDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'supplier-accounts',
    component: SupplierAccountsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  { path: 'pendingBanks', component: PendingComponent, canActivate: [departmentGuard], data: { rbacPermissions: adm } },
  { path: 'cash', loadChildren: () => import('./cash/cash.module').then((m) => m.CashModule) },
  { path: 'capitals', loadChildren: () => import('./capitals/capitals.module').then((m) => m.CapitalsModule) },
  { path: 'bank', loadChildren: () => import('./bank/bank.module').then((m) => m.BankModule) },
  {
    path: 'service-accounts',
    component: ServiceAccountsListComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class FinancialRoutingModule {}
