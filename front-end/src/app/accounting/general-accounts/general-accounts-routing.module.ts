import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { GeneralAccountsComponent } from './general-accounts.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: GeneralAccountsComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class GeneralAccountsRoutingModule { }

