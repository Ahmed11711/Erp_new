import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AccountingTreeComponent } from './accounting-tree.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: AccountingTreeComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AccountingTreeRoutingModule { }

