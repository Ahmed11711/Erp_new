import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { NotificationRoutingModule } from './notification-routing.module';
import { NotificationHomeComponent } from './notification-home/notification-home.component';
import { SharedModule } from '../shared/shared.module';
import { MatIcon, MatIconModule } from '@angular/material/icon';
import { RecievedNotificationComponent } from './recieved-notification/recieved-notification.component';
import { SentNotificationComponent } from './sent-notification/sent-notification.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MovesNotificationComponent } from './moves-notification/moves-notification.component';
import { MatMenuModule } from '@angular/material/menu';


@NgModule({
  declarations: [
    NotificationHomeComponent,
    RecievedNotificationComponent,
    SentNotificationComponent,
    MovesNotificationComponent
  ],
  imports: [
    CommonModule,
    NotificationRoutingModule,
    SharedModule,
    MatIconModule,
    NgxPaginationModule,
    MatPaginatorModule,
    ReactiveFormsModule,
    FormsModule,
    MatMenuModule,
  ]
})
export class NotificationModule { }
