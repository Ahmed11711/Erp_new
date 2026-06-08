import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ListCapitalsComponent } from './list-capitals.component';
import { AddCapitalComponent } from './add-capital.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const fin = [...RBAC_ROUTE.financeTeam];

const routes: Routes = [
  {
    path: '',
    component: ListCapitalsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'create',
    component: AddCapitalComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CapitalsRoutingModule {}
