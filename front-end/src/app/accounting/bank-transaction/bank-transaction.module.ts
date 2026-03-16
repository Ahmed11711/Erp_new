import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BankTransactionComponent } from './bank-transaction.component';
import { RouterModule } from '@angular/router';

@NgModule({
  declarations: [
    BankTransactionComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule.forChild([
      { path: '', component: BankTransactionComponent }
    ])
  ]
})
export class BankTransactionModule { }
