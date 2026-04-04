import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormsModule } from '@angular/forms';

import { TrialBalanceRoutingModule } from './trial-balance-routing.module';
import { TrialBalanceComponent } from './trial-balance.component';

@NgModule({
    declarations: [
        TrialBalanceComponent
    ],
    imports: [
        CommonModule,
        TrialBalanceRoutingModule,
        ReactiveFormsModule,
        FormsModule
    ]
})
export class TrialBalanceModule { }
