import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { NotificationHomeComponent } from './notification-home/notification-home.component';
import { RecievedNotificationComponent } from './recieved-notification/recieved-notification.component';
import { SentNotificationComponent } from './sent-notification/sent-notification.component';
import { MovesNotificationComponent } from './moves-notification/moves-notification.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const routes: Routes = [
  { path: '', component: NotificationHomeComponent },
  { path: 'recieved', component: RecievedNotificationComponent },
  { path: 'sent', component: SentNotificationComponent },
  {
    path: 'moves',
    component: MovesNotificationComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.systemAdmin] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class NotificationRoutingModule {}
