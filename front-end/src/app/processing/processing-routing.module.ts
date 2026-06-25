import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { ProcessingDashboardComponent } from './processing-dashboard/processing-dashboard.component';
import { ProcessingOrdersComponent } from './processing-orders/processing-orders.component';
import { ProcessingOrderDetailComponent } from './processing-order-detail/processing-order-detail.component';

const perms = [...RBAC_ROUTE.processingAll];

const routes: Routes = [
  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
  { path: 'dashboard', component: ProcessingDashboardComponent, canActivate: [departmentGuard], data: { rbacPermissions: perms } },
  { path: 'orders', component: ProcessingOrdersComponent, canActivate: [departmentGuard], data: { rbacPermissions: perms } },
  { path: 'orders/:id', component: ProcessingOrderDetailComponent, canActivate: [departmentGuard], data: { rbacPermissions: perms } },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ProcessingRoutingModule {}
