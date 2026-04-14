import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AccountingTreeRoutingModule } from './accounting-tree-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { AccountingTreeComponent } from './accounting-tree.component';

@NgModule({
  declarations: [
    AccountingTreeComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    AccountingTreeRoutingModule,
    SharedModule
  ]
})
export class AccountingTreeModule { }

