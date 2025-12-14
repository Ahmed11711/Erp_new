import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ApprovalsRoutingModule } from './approvals-routing.module';
import { ApprovalsComponent } from './approvals.component';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { SharedModule } from '../shared/shared.module';
import { ApprovalDetailsDialogComponent } from './approval-details-dialog/approval-details-dialog.component';


@NgModule({
  declarations: [
    ApprovalsComponent,
    ApprovalDetailsDialogComponent
  ],
  imports: [
    CommonModule,
    ApprovalsRoutingModule,
    SharedModule,
    MatIconModule,
    NgxPaginationModule,
    MatPaginatorModule,
    ReactiveFormsModule,
    FormsModule,
    MatMenuModule,
  ]
})
export class ApprovalsModule { }
