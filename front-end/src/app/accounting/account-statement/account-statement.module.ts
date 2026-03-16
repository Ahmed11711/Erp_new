import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

import { AccountStatementRoutingModule } from './account-statement-routing.module';
import { AccountStatementComponent } from './account-statement.component';
import { SharedModule } from '../../shared/shared.module';

@NgModule({
  declarations: [
    AccountStatementComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    AccountStatementRoutingModule,
    SharedModule
  ]
})
export class AccountStatementModule { }

