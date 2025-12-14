import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddInvoiceComponent } from './add-invoice/add-invoice.component';
import { ListInvoiceComponent } from './list-invoice/list-invoice.component';
import { PurchaseDetailsComponent } from './purchase-details/purchase-details.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path :'add_invoice', component: AddInvoiceComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path :'list_invoice', component: ListInvoiceComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path :'invoice', component: PurchaseDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PurchasesRoutingModule { }
