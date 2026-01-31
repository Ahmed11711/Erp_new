import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CashRoutingModule } from './cash-routing.module';
import { ClientCashPaymentsComponent } from './client-cash-payments.component';
import { SupplierCashPaymentsComponent } from './supplier-cash-payments.component';
import { SharedModule } from 'src/app/shared/shared.module';
// Assuming SharedModule has HttpClientModule or we might need it for Service. But services are provided in root usually.

@NgModule({
    declarations: [
        ClientCashPaymentsComponent,
        SupplierCashPaymentsComponent
    ],
    imports: [
        CommonModule,
        CashRoutingModule,
        SharedModule
    ]
})
export class CashModule { }
