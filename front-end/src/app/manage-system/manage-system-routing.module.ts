import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddUserComponent } from './add-user/add-user.component';
import { UsersComponent } from './users/users.component';
import { PowersComponent } from './powers/powers.component';
import { FollowUsersComponent } from './follow-users/follow-users.component';
import { SettingsComponent } from './settings/settings.component';
import { RolesManagementComponent } from './roles-management/roles-management.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const sys = [...RBAC_ROUTE.settingsManage];

const routes: Routes = [
  { path: 'adduser', component: AddUserComponent, canActivate: [departmentGuard], data: { rbacPermissions: sys } },
  { path: 'users', component: UsersComponent, canActivate: [departmentGuard], data: { rbacPermissions: sys } },
  { path: 'powers', component: PowersComponent, canActivate: [departmentGuard], data: { rbacPermissions: sys } },
  { path: 'rbac-roles', component: RolesManagementComponent, canActivate: [departmentGuard], data: { rbacPermissions: sys } },
  { path: 'followusers', component: FollowUsersComponent, canActivate: [departmentGuard], data: { rbacPermissions: sys } },
  { path: 'settings', component: SettingsComponent, canActivate: [departmentGuard], data: { rbacPermissions: [...RBAC_ROUTE.shopifySettings] } },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ManageSystemRoutingModule {}
