import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ReportsRoutingModule } from './reports-routing.module';
import { IncomeListComponent } from './income-list/income-list.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../shared/shared.module';
import { TrialBalanceComponent } from './trial-balance/trial-balance.component';
import { FinancialStatementComponent } from './financial-statement/financial-statement.component';
import { ProcurementReportComponent } from './procurement-report/procurement-report.component';
import { ProductSalesComponent } from './product-sales/product-sales.component';
import { StorageComponent } from './storage/storage.component';
import { CategoryComponent } from './category/category.component';
import { ShippincompanyReportsComponent } from './shippincompany-reports/shippincompany-reports.component';


@NgModule({
  declarations: [
    IncomeListComponent,
    TrialBalanceComponent,
    FinancialStatementComponent,
    ProcurementReportComponent,
    ProductSalesComponent,
    StorageComponent,
    CategoryComponent,
    ShippincompanyReportsComponent
  ],
  imports: [
    CommonModule,
    ReportsRoutingModule,
    ReactiveFormsModule,
    FormsModule,
    SharedModule
  ]
})
export class ReportsModule { }
