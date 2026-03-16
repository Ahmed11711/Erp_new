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
import { ProductPerformanceComponent } from './product-performance/product-performance.component';
import { LeadActivityReportComponent } from './lead-activity-report/lead-activity-report.component';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorModule } from '@angular/material/paginator';


@NgModule({
  declarations: [
    IncomeListComponent,
    TrialBalanceComponent,
    FinancialStatementComponent,
    ProcurementReportComponent,
    ProductSalesComponent,
    StorageComponent,
    CategoryComponent,
    ShippincompanyReportsComponent,
    ProductPerformanceComponent,
    LeadActivityReportComponent
  ],
  imports: [
    CommonModule,
    ReportsRoutingModule,
    ReactiveFormsModule,
    FormsModule,
    SharedModule,
    MatIconModule,
    MatMenuModule,
    MatPaginatorModule
  ]
})
export class ReportsModule { }
