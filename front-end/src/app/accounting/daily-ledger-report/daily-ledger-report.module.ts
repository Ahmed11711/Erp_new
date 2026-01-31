import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DailyLedgerReportRoutingModule } from './daily-ledger-report-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { DailyLedgerReportComponent } from './daily-ledger-report.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

@NgModule({
  declarations: [
    DailyLedgerReportComponent
  ],
  imports: [
    CommonModule,
    DailyLedgerReportRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule
  ]

})
export class DailyLedgerReportModule { }

