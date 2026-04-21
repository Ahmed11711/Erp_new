import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { ShopifyIntegrationDashboardComponent } from './shopify-integration-dashboard/shopify-integration-dashboard.component';

const routes: Routes = [
  {
    path: '',
    component: ShopifyIntegrationDashboardComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Logistics Specialist'] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ShopifyIntegrationRoutingModule {}
