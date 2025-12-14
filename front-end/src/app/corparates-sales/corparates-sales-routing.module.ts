import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { departmentGuard } from '../guards/department.guard';
import { LeadListComponent } from './lead-list/lead-list.component';
import { LeadAddEditComponent } from './lead-add-edit/lead-add-edit.component';
import { LeadDetailsComponent } from './lead-details/lead-details.component';

const routes: Routes = [
  {path:'leads', component:LeadListComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management']}
  },
  {path:'leads/:id', component:LeadDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management']}
  },
  {path:'add-lead', component:LeadAddEditComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin', 'Shipping Management']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CorparatesSalesRoutingModule { }
