import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { ShopifyIntegrationDashboardComponent } from './shopify-integration-dashboard/shopify-integration-dashboard.component';

const routes: Routes = [
  {
    path: '',
    component: ShopifyIntegrationDashboardComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.shopifyDashboard] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ShopifyIntegrationRoutingModule {}
