import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { HomeComponent } from './home.component';
import { CategoriesReportComponent } from './categories-report/categories-report.component';
import { CategoriesStatusReportComponent } from './categories-status-report/categories-status-report.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const routes: Routes = [
  { path: '', component: HomeComponent },
  {
    path: 'categoriesreports',
    component: CategoriesReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.categoriesReportsHome] },
  },
  {
    path: 'categoriesstatusreports',
    component: CategoriesStatusReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.categoriesReportsHome] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class HomeRoutingModule {}
