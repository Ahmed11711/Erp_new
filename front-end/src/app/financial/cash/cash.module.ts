import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CashRoutingModule } from './cash-routing.module';
import { ClientCashPaymentsComponent } from './client-cash-payments.component';
import { SupplierCashPaymentsComponent } from './supplier-cash-payments.component';
import { SharedModule } from 'src/app/shared/shared.module';
// Assuming SharedModule has HttpClientModule or we might need it for Service. But services are provided in root usually.

import { FormsModule } from '@angular/forms';
import { CashReceiveFromClientComponent } from './receive-from-client/receive-from-client.component';
import { CashGiveToClientComponent } from './give-to-client/give-to-client.component';
import { CashReceiveFromSupplierComponent } from './receive-from-supplier/receive-from-supplier.component';
import { CashPayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';

@NgModule({
    declarations: [
        ClientCashPaymentsComponent,
        SupplierCashPaymentsComponent,
        CashReceiveFromClientComponent,
        CashGiveToClientComponent,
        CashReceiveFromSupplierComponent,
        CashPayToSupplierComponent
    ],
    imports: [
        CommonModule,
        CashRoutingModule,
        SharedModule,
        FormsModule
    ]
})
export class CashModule { }
