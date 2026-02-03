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
import { EditexpenseComponent } from './editexpense/editexpense.component';
import { PendingComponent } from './pending/pending.component';
import { departmentGuard } from '../guards/department.guard';
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


const routes: Routes = [
  {
    path: 'banks', component: ListBanksComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'addexpense', component: AddExpenseComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'editexpense/:id', component: EditexpenseComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'expenses', component: ExpensesComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'expenseskind', component: ExpensesKindComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'banks-movements', component: BanksMovementsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'income-list', component: IncomeListComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'addincome', component: AddIncomeComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'otherincome', component: OtherIncomeComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'estates', component: EstatesComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'estates/category/:id', component: AssetCategoryComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'estates/sub-category/:id', component: AssetSubCategoryComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'estates/sub-category-end/:id', component: AssetSubSubCategoryComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'addestate', component: AddEstateComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'discounts', component: DiscountsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'addcimmitment', component: AddCommitmentComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'individualsclients', component: IndividualsClientsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'covenant', component: CovenantComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'addcovenant', component: AddCovenantComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'expense_details/:id', component: ExpenseDetailsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'bank_details/:id', component: BankDetailsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'customer-accounts', component: CustomerAccountsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'report-order-new', component: ReportNewOrdersComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'report-order-new-details', component: ReportNewOrdersComponentDetails,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'customer-accounts/customer-account-details', component: CustomerAccountDetailsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'supplier-accounts', component: SupplierAccountsComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  },
  {
    path: 'pendingBanks', component: PendingComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin'] }
  },
  {
    path: 'cash',
    loadChildren: () => import('./cash/cash.module').then(m => m.CashModule)
  },
  {
    path: 'capitals',
    loadChildren: () => import('./capitals/capitals.module').then(m => m.CapitalsModule)
  },
  {
    path: 'bank',
    loadChildren: () => import('./bank/bank.module').then(m => m.BankModule)
  },
  {
    path: 'service-accounts', component: ServiceAccountsListComponent,
    canActivate: [departmentGuard], data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FinancialRoutingModule { }
