import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CashRoutingModule } from './cash-routing.module';
import { PreviousCashPaymentsComponent } from './previous-cash-payments/previous-cash-payments.component';
import { SharedModule } from 'src/app/shared/shared.module';

import { FormsModule } from '@angular/forms';
import { MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { ReceivePaymentComponent } from './receive-payment/receive-payment.component';
import { CashGiveToClientComponent } from './give-to-client/give-to-client.component';
import { CashPayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';
import { CashVoucherEditDialogComponent } from './cash-voucher-edit-dialog/cash-voucher-edit-dialog.component';

@NgModule({
    declarations: [
        PreviousCashPaymentsComponent,
        ReceivePaymentComponent,
        CashGiveToClientComponent,
        CashPayToSupplierComponent,
        CashVoucherEditDialogComponent,
    ],
    imports: [
        CommonModule,
        CashRoutingModule,
        SharedModule,
        FormsModule,
        MatDialogModule,
        MatButtonModule,
    ]
})
export class CashModule { }
