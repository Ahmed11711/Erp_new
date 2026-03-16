import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { ServiceTransactionComponent } from './service-transaction.component';
import { RouterModule } from '@angular/router';

@NgModule({
  declarations: [
    ServiceTransactionComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    RouterModule.forChild([
      { path: '', component: ServiceTransactionComponent }
    ])
  ]
})
export class ServiceTransactionModule { }
