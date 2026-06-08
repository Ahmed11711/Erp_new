import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DailyLedgerReportComponent } from './daily-ledger-report.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const routes: Routes = [
  {
    path: '',
    component: DailyLedgerReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: [...RBAC_ROUTE.financeTeam] },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class DailyLedgerReportRoutingModule {}
