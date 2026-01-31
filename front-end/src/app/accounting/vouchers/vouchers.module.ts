import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { VouchersRoutingModule } from './vouchers-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { VouchersComponent } from './vouchers.component';
import { VoucherDialogComponent } from './voucher-dialog/voucher-dialog.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

@NgModule({
  declarations: [
    VouchersComponent,
    VoucherDialogComponent
  ],
  imports: [
    CommonModule,
    VouchersRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule
  ]

})
export class VouchersModule { }

