import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { FinancialRoutingModule } from './financial-routing.module';
import { ListBanksComponent } from './list-banks/list-banks.component';
import { SharedModule } from "../shared/shared.module";
import { DialogComponent } from './dialog/dialog.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
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
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { CovenantComponent } from './covenant/covenant.component';
import { AddCovenantComponent } from './add-covenant/add-covenant.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { ExpenseDetailsComponent } from './expense-details/expense-details.component';
import { BankDetailsComponent } from './bank-details/bank-details.component';
import { EditexpenseComponent } from './editexpense/editexpense.component';
import { PendingComponent } from './pending/pending.component';
import { BanksMovementsComponent } from './banks-movements/banks-movements.component';
import { BankMovementDetailsDialogComponent } from './bank-movement-details-dialog/bank-movement-details-dialog.component';
import { BankMovementCustodyDialogComponent } from './bank-movement-custody-dialog/bank-movement-custody-dialog.component';
import { IncomeListComponent } from './income-list/income-list.component';
import { CustomerAccountsComponent } from './customer-accounts/customer-accounts.component';
import { SupplierAccountsComponent } from './supplier-accounts/supplier-accounts.component';
import { CustomerAccountDetailsComponent } from './customer-account-details/customer-account-details.component';
import { AssetCategoryComponent } from './asset-category/asset-category.component';
import { DialogAssetComponent } from './dialog-asset/dialog-asset.component';
import { AssetSubCategoryComponent } from './asset-sub-category/asset-sub-category.component';
import { AssetSubSubCategoryComponent } from './asset-sub-categoryEnd/asset-sub-category-end.component';
import { ReportNewOrdersComponent } from './V2/report-new-order/report-new-order.component';
import { ReportNewOrdersComponentDetails } from './V2/report-new-order-details/report-new-order-details.component';
  
@NgModule({
    declarations: [
      ListBanksComponent,
      DialogComponent,
      DialogAssetComponent,
      AddExpenseComponent,
      ExpensesComponent,
      ExpensesKindComponent,
      AddIncomeComponent,
      OtherIncomeComponent,
      EstatesComponent,
      AddEstateComponent,
      DiscountsComponent,
      AddCommitmentComponent,
      IndividualsClientsComponent,
      CovenantComponent,
      AddCovenantComponent,
      ExpenseDetailsComponent,
      BankDetailsComponent,
      EditexpenseComponent,
      PendingComponent,
      BanksMovementsComponent,
      BankMovementDetailsDialogComponent,
      BankMovementCustodyDialogComponent,
      IncomeListComponent,
      CustomerAccountsComponent,
      SupplierAccountsComponent,
      CustomerAccountDetailsComponent,
      AssetCategoryComponent,
      AssetSubCategoryComponent,
      AssetSubSubCategoryComponent,
      ReportNewOrdersComponent,
      ReportNewOrdersComponentDetails

      
    ],
    imports: [
        CommonModule,
        FinancialRoutingModule,
        SharedModule,
        ReactiveFormsModule,
        FormsModule,
        MatIconModule,
        MatMenuModule,
        NgxPaginationModule,
        MatPaginatorModule,

    ]
})
export class FinancialModule { }
