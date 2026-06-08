import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CostCentersComponent } from './cost-centers.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const fin = [...RBAC_ROUTE.financeTeam];

const routes: Routes = [
  {
    path: '',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, viewMode: 'list' },
  },
  {
    path: 'tree',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, viewMode: 'tree' },
  },
  {
    path: 'create',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin, action: 'create' },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CostCentersRoutingModule {}
