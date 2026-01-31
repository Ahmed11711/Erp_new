import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BankRoutingModule } from './bank-routing.module';
import { ReceiveFromClientComponent } from './receive-from-client/receive-from-client.component';
import { PayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';

@NgModule({
    declarations: [
        ReceiveFromClientComponent,
        PayToSupplierComponent
    ],
    imports: [
        CommonModule,
        BankRoutingModule,
        FormsModule,
        ReactiveFormsModule
    ]
})
export class BankModule { }
