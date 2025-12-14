import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { IncomeListComponent } from './income-list/income-list.component';
import { TrialBalanceComponent } from './trial-balance/trial-balance.component';
import { FinancialStatementComponent } from './financial-statement/financial-statement.component';
import { ProcurementReportComponent } from './procurement-report/procurement-report.component';
import { ProductSalesComponent } from './product-sales/product-sales.component';
import { StorageComponent } from './storage/storage.component';
import { CategoryComponent } from './category/category.component';
import { ShippincompanyReportsComponent } from './shippincompany-reports/shippincompany-reports.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:"incomelist" , component:IncomeListComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"trialbalance" , component:TrialBalanceComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"financialstatement" , component:FinancialStatementComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"procurement" , component:ProcurementReportComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"productsales" , component:ProductSalesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"storage" , component:StorageComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"category" , component:CategoryComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"shippingcompany" , component:ShippincompanyReportsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ReportsRoutingModule { }
