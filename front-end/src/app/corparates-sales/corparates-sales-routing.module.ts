import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { LeadListComponent } from './lead-list/lead-list.component';
import { LeadAddEditComponent } from './lead-add-edit/lead-add-edit.component';
import { LeadDetailsComponent } from './lead-details/lead-details.component';
import { LeadStatusManagementComponent } from '../lead-status-management/lead-status-management.component';
import { FollowUpLeadsComponent } from './follow-up-leads/follow-up-leads.component';

const corp = [...RBAC_ROUTE.corporate];

const routes: Routes = [
  { path: 'leads', component: LeadListComponent, canActivate: [departmentGuard], data: { rbacPermissions: corp } },
  { path: 'leads/:id', component: LeadDetailsComponent, canActivate: [departmentGuard], data: { rbacPermissions: corp } },
  { path: 'add-lead', component: LeadAddEditComponent, canActivate: [departmentGuard], data: { rbacPermissions: corp } },
  {
    path: 'lead-status-management',
    component: LeadStatusManagementComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: corp },
  },
  {
    path: 'follow-up-leads',
    component: FollowUpLeadsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: corp },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CorparatesSalesRoutingModule {}
