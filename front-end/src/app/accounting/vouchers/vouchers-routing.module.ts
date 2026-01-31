import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { VouchersComponent } from './vouchers.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: 'clients',
    component: VouchersComponent,
    canActivate: [departmentGuard],
    data: {
      allowedDepartments: ['Admin', 'Account Management'],
      voucherType: 'client'
    }
  },
  {
    path: 'suppliers',
    component: VouchersComponent,
    canActivate: [departmentGuard],
    data: {
      allowedDepartments: ['Admin', 'Account Management'],
      voucherType: 'supplier'
    }
  },
  {
    path: '',
    redirectTo: 'clients',
    pathMatch: 'full'
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class VouchersRoutingModule { }

