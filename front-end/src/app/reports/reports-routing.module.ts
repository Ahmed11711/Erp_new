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
import { ProductPerformanceComponent } from './product-performance/product-performance.component';
import { LeadActivityReportComponent } from './lead-activity-report/lead-activity-report.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const repFin = [...RBAC_ROUTE.reportsFinance];
const repCorp = [...RBAC_ROUTE.reportsCorporate];
const repAccounting = [...RBAC_ROUTE.reportsAccountingWide];
const repWarehouse = [...RBAC_ROUTE.reportsWarehouse];
const repProcurement = [...RBAC_ROUTE.reportsProcurement];
const repProductSales = [...RBAC_ROUTE.reportsProductSales];
const repCategoryProfit = [...RBAC_ROUTE.reportsCategoryProfit];
const repShippingCompanies = [...RBAC_ROUTE.reportsShippingCompanies];

const routes: Routes = [
  { path: 'incomelist', component: IncomeListComponent, canActivate: [departmentGuard], data: { rbacPermissions: repAccounting } },
  { path: 'trialbalance', component: TrialBalanceComponent, canActivate: [departmentGuard], data: { rbacPermissions: repAccounting } },
  {
    path: 'financialstatement',
    component: FinancialStatementComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: repFin },
  },
  { path: 'procurement', component: ProcurementReportComponent, canActivate: [departmentGuard], data: { rbacPermissions: repProcurement } },
  { path: 'productsales', component: ProductSalesComponent, canActivate: [departmentGuard], data: { rbacPermissions: repProductSales } },
  {
    path: 'product-performance',
    component: ProductPerformanceComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: repFin },
  },
  { path: 'storage', component: StorageComponent, canActivate: [departmentGuard], data: { rbacPermissions: repWarehouse } },
  { path: 'category', component: CategoryComponent, canActivate: [departmentGuard], data: { rbacPermissions: repCategoryProfit } },
  {
    path: 'shippingcompany',
    component: ShippincompanyReportsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: repShippingCompanies },
  },
  {
    path: 'lead-activity',
    component: LeadActivityReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: repCorp },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ReportsRoutingModule {}
