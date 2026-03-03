import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { CashTransactionComponent } from './cash-transaction.component';
import { RouterModule } from '@angular/router';

@NgModule({
  declarations: [
    CashTransactionComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule.forChild([
      { path: '', component: CashTransactionComponent }
    ])
  ]
})
export class CashTransactionModule { }
