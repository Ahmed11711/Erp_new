import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DailyEntriesComponent } from './daily-entries.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: DailyEntriesComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class DailyEntriesRoutingModule { }

