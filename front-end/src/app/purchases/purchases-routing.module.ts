import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddInvoiceComponent } from './add-invoice/add-invoice.component';
import { ListInvoiceComponent } from './list-invoice/list-invoice.component';
import { PurchaseDetailsComponent } from './purchase-details/purchase-details.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const pur = [...RBAC_ROUTE.purchases];

const routes: Routes = [
  {
    path: 'add_invoice/:id',
    component: AddInvoiceComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: pur },
  },
  {
    path: 'add_invoice',
    component: AddInvoiceComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: pur },
  },
  {
    path: 'list_invoice',
    component: ListInvoiceComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: pur },
  },
  {
    path: 'invoice/:id',
    component: PurchaseDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: pur },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class PurchasesRoutingModule {}
