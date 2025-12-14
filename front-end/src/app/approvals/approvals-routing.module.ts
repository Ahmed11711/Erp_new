import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ApprovalsComponent } from './approvals.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [{ path: '', component: ApprovalsComponent,
  canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
}];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ApprovalsRoutingModule { }
