import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { LeadListComponent } from './lead-list/lead-list.component';
import { LeadAddEditComponent } from './lead-add-edit/lead-add-edit.component';
import { LeadDetailsComponent } from './lead-details/lead-details.component';
import { LeadStatusManagementComponent } from '../lead-status-management/lead-status-management.component';
import { FollowUpLeadsComponent } from './follow-up-leads/follow-up-leads.component';

const routes: Routes = [
  {path:'leads', component:LeadListComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management','Corparates']}
  },
  {path:'leads/:id', component:LeadDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management','Corparates']}
  },
  {path:'add-lead', component:LeadAddEditComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management','Corparates']}
  },
  {path:'lead-status-management', component:LeadStatusManagementComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management','Corparates']}
  },
  {path:'follow-up-leads', component:FollowUpLeadsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management','Corparates']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CorparatesSalesRoutingModule { }
