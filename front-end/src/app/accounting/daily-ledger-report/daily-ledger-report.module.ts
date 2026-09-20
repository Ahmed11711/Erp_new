import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DailyLedgerReportRoutingModule } from './daily-ledger-report-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { DailyLedgerReportComponent } from './daily-ledger-report.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatAutocompleteModule } from '@angular/material/autocomplete';

@NgModule({
  declarations: [
    DailyLedgerReportComponent
  ],
  imports: [
    CommonModule,
    DailyLedgerReportRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule,
    MatAutocompleteModule
  ]

})
export class DailyLedgerReportModule { }

