import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AccountingRoutingModule } from './accounting-routing.module';
import { SharedModule } from '../shared/shared.module';

@NgModule({
  declarations: [
    // Components will be added here
  ],
  imports: [
    CommonModule,
    AccountingRoutingModule,
    SharedModule
  ]
})
export class AccountingModule { }

