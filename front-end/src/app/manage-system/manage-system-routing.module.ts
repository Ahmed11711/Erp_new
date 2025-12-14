import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddUserComponent } from './add-user/add-user.component';
import { UsersComponent } from './users/users.component';
import { PowersComponent } from './powers/powers.component';
import { FollowUsersComponent } from './follow-users/follow-users.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:'adduser' , component:AddUserComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'users' , component:UsersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'powers' , component:PowersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'followusers' , component:FollowUsersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ManageSystemRoutingModule { }
