import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CostCentersComponent } from './cost-centers.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'], viewMode: 'list' }
  },
  {
    path: 'tree',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'], viewMode: 'tree' }
  },
  {
    path: 'create',
    component: CostCentersComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'], action: 'create' }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CostCentersRoutingModule { }

