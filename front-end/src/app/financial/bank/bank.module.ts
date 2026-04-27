import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BankRoutingModule } from './bank-routing.module';
import { PayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';

@NgModule({
    declarations: [
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
