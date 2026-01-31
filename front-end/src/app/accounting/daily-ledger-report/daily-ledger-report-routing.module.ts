import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DailyLedgerReportComponent } from './daily-ledger-report.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: DailyLedgerReportComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DailyLedgerReportRoutingModule { }

