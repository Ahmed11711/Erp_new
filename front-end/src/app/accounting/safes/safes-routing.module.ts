import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SafesComponent } from './safes.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: SafesComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SafesRoutingModule { }

